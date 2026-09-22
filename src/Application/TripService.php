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
use App\Domain\Trip\RoomReservation;
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
            'adultAgeThreshold',
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

        $endDate = null;
        if (array_key_exists('endDate', $payload) && $payload['endDate'] !== '' && $payload['endDate'] !== null) {
            try {
                $endDate = new DateTimeImmutable((string) $payload['endDate']);
            } catch (Exception) {
                throw new ValidationException(['endDate' => 'invalid_date']);
            }
            if ($endDate < $startDate) {
                throw new ValidationException(['endDate' => 'must_be_after_start_date']);
            }
        }

        $reservationPayloads = $this->roomReservationPayloads($payload);

        $bookings = [];
        try {
            foreach ($payload['bookings'] as $bookingPayload) {
                $salesPriceRaw = $bookingPayload['salesPricePerPerson'] ?? null;
                $salesPrice = ($salesPriceRaw === null || $salesPriceRaw === '')
                    ? null
                    : (float) $salesPriceRaw;
                $bookings[] = new RoomBooking(
                    RoomCategoryType::from((string) $bookingPayload['categoryType']),
                    (int) $bookingPayload['count'],
                    (float) $bookingPayload['basePricePerPerson'],
                    $salesPrice
                );
            }

            $expenses = [];
            foreach ((array) $payload['groupExpenses'] as $expensePayload) {
                $expenses[] = new GroupExpense(
                    (string) $expensePayload['label'],
                    (float) $expensePayload['amount']
                );
            }

            $reservations = array_map(
                static fn (array $reservation) => new RoomReservation($reservation['roomType'], $reservation['count']),
                $reservationPayloads
            );

            $trip = new Trip(
                id: null,
                name: (string) $payload['name'],
                startDate: $startDate,
                pricingPolicy: new TripPricingPolicy(
                    new Percentage((float) $payload['markupPercent']),
                    new Percentage((float) $payload['clubFeePercent']),
                    DistributionMethod::from((string) $payload['distributionMethod']),
                    (float) $payload['spaTaxPerPerson'],
                    (int) $payload['spaTaxAgeThreshold'],
                    (int) $payload['adultAgeThreshold'],
                    // Optional, damit bestehende Clients unveraendert weiterlaufen.
                    (bool) ($payload['averageAdultPrice'] ?? false)
                ),
                bookings: $bookings,
                groupExpenses: $expenses,
                plannedTotalCosts: (float) ($payload['plannedTotalCosts'] ?? 0),
                plannedTotalRevenue: (float) ($payload['plannedTotalRevenue'] ?? 0),
                spaTaxCount: (int) ($payload['spaTaxCount'] ?? 0),
                endDate: $endDate,
                roomReservations: $reservations,
            );
        } catch (Throwable $exception) {
            throw new ValidationException(['payload' => $exception->getMessage()]);
        }

        return $id === null ? $trip : $trip->withId($id);
    }

    /**
     * Optional, damit bestehende Clients unveraendert weiterlaufen. Eine ganz leere
     * Zeile (kein Typ, Anzahl 0) ist ein nicht ausgefuelltes Formularfeld und faellt
     * weg; ohne Typ, aber mit Anzahl, ist sie ein Eingabefehler.
     *
     * @return array<int, array{roomType: string, count: int}>
     */
    private function roomReservationPayloads(array $payload): array
    {
        $result = [];
        $seen = [];

        foreach ((array) ($payload['roomReservations'] ?? []) as $reservationPayload) {
            $roomType = trim((string) ($reservationPayload['roomType'] ?? ''));
            $count = (int) ($reservationPayload['count'] ?? 0);

            if ($roomType === '') {
                if ($count === 0) {
                    continue;
                }
                throw new ValidationException(['roomReservations' => 'room_type_required']);
            }

            $key = RoomReservation::matchKeyFor($roomType);
            if (isset($seen[$key])) {
                throw new ValidationException(['roomReservations' => 'duplicate_room_type']);
            }
            $seen[$key] = true;

            $result[] = ['roomType' => $roomType, 'count' => $count];
        }

        return $result;
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




