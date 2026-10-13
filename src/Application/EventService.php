<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\EventRepositoryInterface;
use App\Domain\Event\Event;
final readonly class EventService
{
    public function __construct(private EventRepositoryInterface $events, private AccessPolicy $policy, private TripListService $trips) {}
    public function create(array $payload): array
    {
        $this->policy->actorId();
        return $this->policy->transaction(function () use ($payload) {
            $event = $this->events->save($this->map(null, $payload));
            $this->policy->initializeEvent($event->id); return $this->get($event->id);
        });
    }
    public function requireActive(int $id): Event
    {
        $this->policy->require('event', $id, 'event.manage');
        $event = $this->events->get($id) ?? throw new NotFoundException('Event not found');
        if ($event->archived) { throw new AccessException('event_archived', 409); }
        return $event;
    }
    public function update(int $id, array $payload): array
    {
        return $this->policy->transaction(function () use ($id, $payload) {
            $this->policy->require('event', $id, 'event.manage');
            $existing = $this->events->get($id) ?? throw new NotFoundException('Event not found');
            if (!isset($payload['revision']) || (int) $payload['revision'] !== $existing->revision) { throw new AccessException('revision_conflict', 409); }
            $event = $this->events->save($this->map($id, $payload));
            $this->policy->audit($event->archived ? 'event.archive' : 'event.update', 'event', $id, 'Stammdaten aktualisiert');
            return $this->get($id);
        });
    }
    public function list(string $query = '', bool $archived = false): array
    {
        $trips = $this->trips->listTrips(); $result = [];
        foreach ($this->events->all() as $event) {
            $allowed = array_values(array_filter($trips, static fn ($trip) => $trip['eventId'] === $event->id));
            if (!$this->policy->can('event', $event->id, 'event.view') && $allowed === []) { continue; }
            if ($event->archived !== $archived || ($query !== '' && !str_contains(mb_strtolower($event->name . ' ' . $event->description), mb_strtolower($query)))) { continue; }
            $next = array_values(array_filter($allowed, static fn ($trip) => $trip['startDate'] >= date('Y-m-d')));
            usort($next, static fn ($a, $b) => strcmp($a['startDate'], $b['startDate']));
            $result[] = $event->toArray() + ['tripCount' => count($allowed), 'nextTripDate' => $next[0]['startDate'] ?? null, 'capabilities' => $this->policy->capabilities('event', $event->id)];
        }
        return $result;
    }
    public function get(int $id): array
    {
        $trips = array_values(array_filter($this->trips->listTrips(), static fn ($trip) => $trip['eventId'] === $id));
        if (!$this->policy->can('event', $id, 'event.view') && $trips === []) { throw new AccessException('forbidden'); }
        $event = $this->events->get($id) ?? throw new NotFoundException('Event not found');
        return $event->toArray() + ['trips' => $trips, 'capabilities' => $this->policy->capabilities('event', $id)];
    }
    public function assign(int $tripId, ?int $eventId, string $reason): void
    {
        $this->policy->writeTrip($tripId, 'trip.manage', function () use ($tripId, $eventId, $reason) {
            $reason = $this->policy->reason($reason);
            if (($this->policy->metadata($tripId)['form_version_id'] ?? null) !== null) { throw new AccessException('bound_form_prevents_event_change', 409); }
            $previous = $this->policy->metadata($tripId)['event_id'] ?? null;
            if ($previous !== null) { $this->policy->require('event', (int) $previous, 'event.manage'); }
            if ($eventId !== null) { $this->requireActive($eventId); }
            $this->policy->setMetadata($tripId, ['event_id' => $eventId]);
            $this->policy->audit('trip.assign', 'trip', $tripId, $reason . ' · eventId=' . ($eventId ?? 'null'));
        });
    }
    private function map(?int $id, array $p): Event
    {
        if (isset($p['archived']) && !is_bool($p['archived'])) { throw new ValidationException(['archived' => 'boolean_required']); }
        try { return new Event($id, trim((string) ($p['name'] ?? '')), trim((string) ($p['description'] ?? '')), $p['archived'] ?? false, (int) ($p['revision'] ?? 1)); }
        catch (\InvalidArgumentException $e) { throw new ValidationException(['name' => $e->getMessage()]); }
    }
}
