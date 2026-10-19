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
 * @phpstan-type TripListItem array{id:int,registrationMode:string,eventId:?int,archivedAt:?string,closedAt:?string,name:string,startDate:string,endDate:?string,nights:int,plannedParticipants:?int,registeredParticipants:?int,surplus:?float,surplusBasis:?string}
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
     * @return list<TripListItem>
     *
     * plannedParticipants:int,registeredParticipants:int,surplus:?float,surplusBasis:?string}>
     */
    public function listTrips(bool $archived = false): array
    {
        $items = [];
        foreach ($this->tripRepository->findAll() as $row) {
            if (!$this->policy->can('trip', (int) $row['id'], 'trip.view')) { continue; }
            if ((($this->policy->metadata((int)$row['id'])['archived_at']??null)!==null) !== $archived) { continue; }
            $items[] = $this->toListItem($row);
        }

        return $items;
    }

    /**
     * @param array{id:int,name:string,startDate:string,endDate:?string} $row
     * @return TripListItem
     */
    private function toListItem(array $row): array
    {
        $item = [
            'id' => (int) $row['id'],
            'registrationMode'=>$this->policy->metadata((int)$row['id'])['registration_mode'],
            'eventId' => isset($this->policy->metadata((int) $row['id'])['event_id']) ? (int) $this->policy->metadata((int) $row['id'])['event_id'] : null,
            'archivedAt' => $this->policy->metadata((int) $row['id'])['archived_at'] ?? null,
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

    /** @return array{nights?:int,plannedParticipants?:?int,registeredParticipants?:?int,surplus?:?float,surplusBasis?:?string} */
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
            $registeredParticipants += count(array_filter($registration->participants(),static fn($p)=>$p->status()!=='cancelled'));
        }

        $hasRegistrations = $registeredParticipants > 0;

        return [
            'nights' => $trip->nights(),
            'plannedParticipants' => ($canRegistrations || $canFinance || $this->policy->can('trip', $tripId, 'calculation.manage')) ? $this->plannedParticipants($trip) : null,
            'registeredParticipants' => $canRegistrations ? $registeredParticipants : null,
            'surplus' => !$canFinance ? null : ($hasRegistrations
                ? $this->actualExpenseService->getSettlement($tripId)['surplus']
                : (($snapshot = $this->policy->metadata($tripId)['calculation_snapshot'] ?? null) !== null ? $this->snapshotSurplus($snapshot) : $this->calculator->calculate($trip)['surplus'])),
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

    private function snapshotSurplus(string $json): float
    {
        $data=Input::map(json_decode($json,true,512,JSON_THROW_ON_ERROR));$value=$data['surplus']??null;
        if(!is_float($value)&&!is_int($value))throw new \UnexpectedValueException('Invalid calculation snapshot');
        return (float)$value;
    }
}
