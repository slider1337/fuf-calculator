<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\NotFoundException;
use App\Application\TripService;
use App\Application\ValidationException;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TripController
{
    public function __construct(private TripService $service)
    {
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();

        try {
            $trip = $this->service->createFromArray($payload);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        }

        return JsonResponder::write($response, $this->tripToArray($trip), 201);
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::write($response, $this->service->listTrips());
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();

        try {
            $trip = $this->service->updateFromArray((int) $id, $payload);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->tripToArray($trip));
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $trip = $this->service->getTrip((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->tripToArray($trip));
    }

    public function calculate(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $result = $this->service->calculate((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $result);
    }

    private function tripToArray($trip): array
    {
        return [
            'id' => $trip->id(),
            'name' => $trip->name(),
            'startDate' => $trip->startDate()->format('Y-m-d'),
            'markupPercent' => $trip->pricingPolicy()->markupPercent()->value(),
            'clubFeePercent' => $trip->pricingPolicy()->clubFeePercent()->value(),
            'distributionMethod' => $trip->pricingPolicy()->distributionMethod()->value,
            'spaTaxPerPerson' => $trip->pricingPolicy()->spaTaxPerPerson(),
            'spaTaxAgeThreshold' => $trip->pricingPolicy()->spaTaxAgeThreshold(),
            'bookings' => array_map(static fn ($booking) => [
                'categoryType' => $booking->categoryType()->value,
                'count' => $booking->count(),
                'basePricePerPerson' => $booking->basePricePerPerson(),
            ], $trip->bookings()),
            'groupExpenses' => array_map(static fn ($expense) => [
                'label' => $expense->label(),
                'amount' => $expense->amount(),
            ], $trip->groupExpenses()),
        ];
    }
}




