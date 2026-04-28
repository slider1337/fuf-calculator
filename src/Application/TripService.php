<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\TripRepositoryInterface;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateTimeImmutable;
use Exception;
use Throwable;

final readonly class TripService
{
    public function __construct(
        private TripRepositoryInterface $repository,
        private PriceCalculatorService $calculator
    ) {
    }

    public function createFromArray(array $payload): Trip
    {
        return $this->repository->save($this->mapPayloadToTrip($payload, null));
    }

    public function updateFromArray(int $id, array $payload): Trip
    {
        $existing = $this->repository->getById($id);
        if ($existing === null) {
            throw new NotFoundException('Trip not found.');
        }

        return $this->repository->update($this->mapPayloadToTrip($payload, $id));
    }

    public function listTrips(): array
    {
        return $this->repository->findAll();
    }

    private function mapPayloadToTrip(array $payload, ?int $id): Trip
    {
        $required = [
            'name',
            'startDate',
            'markupPercent',
            'clubFeePercent',
            'distributionMethod',
            'spaTaxPerPerson',
            'spaTaxAgeThreshold',
            'bookings',
            'groupExpenses',
        ];

        $errors = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                $errors[$field] = 'required';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if (!is_array($payload['bookings']) || count($payload['bookings']) !== 3) {
            throw new ValidationException(['bookings' => 'must_contain_three_categories']);
        }

        try {
            $startDate = new DateTimeImmutable((string) $payload['startDate']);
        } catch (Exception) {
            $errors['startDate'] = 'invalid_date';
            throw new ValidationException($errors);
        }

        $bookings = [];
        try {
            foreach ($payload['bookings'] as $bookingPayload) {
                $bookings[] = new RoomBooking(
                    RoomCategoryType::from((string) $bookingPayload['categoryType']),
                    (int) $bookingPayload['count'],
                    (float) $bookingPayload['basePricePerPerson']
                );
            }

            $expenses = [];
            foreach ((array) $payload['groupExpenses'] as $expensePayload) {
                $expenses[] = new GroupExpense(
                    (string) $expensePayload['label'],
                    (float) $expensePayload['amount']
                );
            }

            $trip = new Trip(
                id: null,
                name: (string) $payload['name'],
                startDate: $startDate,
                pricingPolicy: new TripPricingPolicy(
                    new Percentage((float) $payload['markupPercent']),
                    new Percentage((float) $payload['clubFeePercent']),
                    DistributionMethod::from((string) $payload['distributionMethod']),
                    (float) $payload['spaTaxPerPerson'],
                    (int) $payload['spaTaxAgeThreshold']
                ),
                bookings: $bookings,
                groupExpenses: $expenses,
                plannedTotalCosts: (float) ($payload['plannedTotalCosts'] ?? 0),
                plannedTotalRevenue: (float) ($payload['plannedTotalRevenue'] ?? 0)
            );
        } catch (Throwable $exception) {
            throw new ValidationException(['payload' => $exception->getMessage()]);
        }

        return $id === null ? $trip : $trip->withId($id);
    }

    public function getTrip(int $id): Trip
    {
        $trip = $this->repository->getById($id);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        return $trip;
    }

    public function calculate(int $tripId): array
    {
        return $this->calculator->calculate($this->getTrip($tripId));
    }
}




