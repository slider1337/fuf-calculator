<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\NotFoundException;
use App\Application\TripListService;
use App\Application\TripService;
use App\Application\ValidationException;
use App\Infrastructure\Http\JsonResponder;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TripController
{
    public function __construct(
        private TripService $service,
        private TripListService $listService,
        private \App\Application\AccessPolicy $policy
    ) {
    }

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::write($response, $this->listService->listTrips());
    }

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $trip = $this->service->getTrip((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->tripToArray($trip));
    }

    /**
     * @throws JsonException
     */
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
        $meta = $this->policy->metadata($trip->id());
        $data = \App\Application\TripData::fromTrip($trip);
        if (!$this->policy->can('trip', $trip->id(), 'calculation.manage') && !$this->policy->can('trip', $trip->id(), 'settlement.view')) {
            $data = array_intersect_key($data, array_flip(['id', 'name', 'startDate', 'endDate']));
        }
        return $data + [
            'eventId' => $trip->eventId(), 'closedAt' => $trip->closedAt(), 'closedBy' => $trip->closedBy(),
            'capabilities' => $this->policy->capabilities('trip', $trip->id()),
            'calculationSnapshot' => isset($meta['calculation_snapshot']) && $this->policy->can('trip', $trip->id(), 'settlement.view') ? json_decode($meta['calculation_snapshot'], true) : null,
        ];
    }
}
