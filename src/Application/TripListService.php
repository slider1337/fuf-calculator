<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\Port\TripRepositoryInterface;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Trip\Trip;
use Throwable;

/**
 * Read-Model fuer die Reiseliste. Buendelt Stammdaten, Teilnehmerzahlen und den Ueberschuss
 * je Reise, damit die Liste ohne Nachladen je Zeile auskommt.
 */
final readonly class TripListService
{
    public function __construct(
        private TripRepositoryInterface $tripRepository,
        private RegistrationRepositoryInterface $registrationRepository,
        private PriceCalculatorService $calculator,
        private ActualExpenseService $actualExpenseService,
        private AccessPolicy $policy
    ) {
    }

    /**
     * @return array<int, array{id:int,name:string,startDate:string,endDate:?string,nights:int,
     *     plannedParticipants:int,registeredParticipants:int,surplus:?float,surplusBasis:?string}>
     */
    public function listTrips(): array
    {
        $items = [];
        foreach ($this->tripRepository->findAll() as $row) {
            if (!$this->policy->can('trip', (int) $row['id'], 'trip.view')) { continue; }
            $items[] = $this->toListItem($row);
        }

        return $items;
    }

    private function toListItem(array $row): array
    {
        $item = [
            'id' => (int) $row['id'],
            'eventId' => isset($this->policy->metadata((int) $row['id'])['event_id']) ? (int) $this->policy->metadata((int) $row['id'])['event_id'] : null,
            'closedAt' => $this->policy->metadata((int) $row['id'])['closed_at'] ?? null,
            'name' => (string) $row['name'],
            'startDate' => (string) $row['startDate'],
            'endDate' => $row['endDate'] ?? null,
            'nights' => 0,
            'plannedParticipants' => 0,
            'registeredParticipants' => 0,
            'surplus' => null,
            'surplusBasis' => null,
        ];

        try {
            return array_merge($item, $this->computeFor((int) $row['id']));
        } catch (Throwable) {
            // Eine Reise mit unvollstaendigen Daten darf nicht die gesamte Liste kippen.
            // Sie erscheint dann ohne Kennzahlen statt gar nicht.
            return $item;
        }
    }

    private function computeFor(int $tripId): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            return [];
        }

        $canRegistrations = $this->policy->can('trip', $tripId, 'registration.view');
        $canFinance = $this->policy->can('trip', $tripId, 'settlement.view');
        $registrations = ($canRegistrations || $canFinance) ? $this->registrationRepository->findByTripId($tripId) : [];

        $registeredParticipants = 0;
        foreach ($registrations as $registration) {
            $registeredParticipants += count($registration->participants());
        }

        $hasRegistrations = $registrations !== [];

        return [
            'nights' => $trip->nights(),
            'plannedParticipants' => ($canRegistrations || $canFinance || $this->policy->can('trip', $tripId, 'calculation.manage')) ? $this->plannedParticipants($trip) : null,
            'registeredParticipants' => $canRegistrations ? $registeredParticipants : null,
            'surplus' => !$canFinance ? null : ($hasRegistrations
                ? $this->actualExpenseService->getSettlement($tripId)['surplus']
                : (($snapshot = $this->policy->metadata($tripId)['calculation_snapshot'] ?? null) !== null ? json_decode($snapshot, true)['surplus'] : $this->calculator->calculate($trip)['surplus'])),
            'surplusBasis' => $canFinance ? ($hasRegistrations ? 'settlement' : 'calculation') : null,
        ];
    }

    private function plannedParticipants(Trip $trip): int
    {
        $total = 0;
        foreach ($trip->bookings() as $booking) {
            $total += $booking->count();
        }

        return $total;
    }
}
