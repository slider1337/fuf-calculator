<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\AccessRepositoryInterface;
use App\Application\Port\ActorInterface;

/**
 * @phpstan-import-type TripMetadata from \App\Application\Port\AccessRepositoryInterface
 * @phpstan-import-type MetadataUpdate from \App\Application\Port\AccessRepositoryInterface
 * @phpstan-import-type TeamMember from \App\Application\Port\AccessRepositoryInterface
 * @phpstan-import-type LegacyCandidate from \App\Application\Port\AccessRepositoryInterface
 */
final readonly class AccessPolicy implements \App\Application\Port\BookingPolicy
{
    public const TRIP_CAPABILITIES = ['trip.view', 'trip.manage', 'registration.view', 'registration.manage', 'answers.view', 'answers.correct', 'answers.export', 'analytics.view', 'calculation.manage', 'settlement.view', 'settlement.close', 'settlement.reopen', 'expense.view', 'expense.manage', 'term.manage', 'access.manage'];
    public const EVENT_CAPABILITIES = ['event.view', 'event.manage', 'form.manage', 'form.publish', 'access.manage'];
    public function __construct(private AccessRepositoryInterface $access, private ActorInterface $actor) {}
    public function actorId(): int
    {
        $id = $this->actor->userId();
        if ($id === null || $this->access->role($id) === null) { throw new AccessException('unauthorized', 401); }
        return $id;
    }
    public function admin(): void
    {
        if ($this->access->role($this->actorId()) !== 'admin') { throw new AccessException('forbidden'); }
    }
    public function isAdmin(): bool { return $this->access->role($this->actorId()) === 'admin'; }
    public function can(string $type, int $id, string $capability): bool
    {
        return in_array($capability, $this->access->capabilities($type, $id, $this->actorId()), true);
    }
    public function require(string $type, int $id, string $capability): void
    {
        if (!$this->can($type, $id, $capability)) {
            if ($type === 'trip' && !$this->can('trip', $id, 'trip.view')) { throw new AccessException('not_found', 404); }
            throw new AccessException('forbidden');
        }
    }
    public function trip(int $id, string $capability, bool $write = false): void
    {
        $this->require('trip', $id, $capability);
        if ($write && ($this->access->metadata($id)['archived_at'] ?? null) !== null) { throw new AccessException('trip_archived', 409); }
        if ($write && ($this->access->metadata($id)['closed_at'] ?? null) !== null) { throw new AccessException('trip_closed', 409); }
        if($write&&$capability==='term.manage'){$this->requireRegistrationMode($id,'easyverein');}
    }
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function writeTrip(int $id, string $capability, callable $operation): mixed
    {
        return $this->access->transaction(function () use ($id, $capability, $operation) {
            $this->trip($id, $capability, true); return $operation();
        });
    }
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transaction(callable $operation): mixed { return $this->access->transaction($operation); }
    public function requireRegistrationMode(int $id,string $mode): void
    {
        if(($this->metadata($id)['registration_mode'])!==$mode){throw new AccessException('registration_mode_'.$mode.'_required',409);}
    }
    /** @return TripMetadata */
    public function metadata(int $id): array { return $this->access->metadata($id); }
    /** @param MetadataUpdate $values */
    public function setMetadata(int $id, array $values): void { $this->access->setMetadata($id, $values); }
    /** @return list<string> */
    public function capabilities(string $type, int $id): array { return $this->access->capabilities($type, $id, $this->actorId()); }
    /** @return list<TeamMember> */
    public function team(string $type, int $id): array
    {
        $this->manageAccess($type === 'default' ? 'event' : $type, $id);
        return $this->access->team($type, $id);
    }
    /** @return list<LegacyCandidate> */
    public function candidates(): array { $this->admin(); return $this->access->legacyCandidates(); }
    private function manageAccess(string $type, int $id): void
    {
        if (!$this->isAdmin()) { $this->require($type, $id, 'access.manage'); }
        if (!$this->access->exists($type, $id)) { throw new NotFoundException('Access target not found'); }
    }
    public function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) > 500) { throw new ValidationException(['reason' => 'required_max_500']); }
        return $reason===''?'Änderung gespeichert':$reason;
    }
    /** @param list<string> $capabilities */
    public function grant(string $type, int $id, int $userId, array $capabilities, string $reason): void
    {
        $this->transaction(function () use ($type, $id, $userId, $capabilities, $reason) {
            $this->manageAccess($type === 'default' ? 'event' : $type, $id);
            $allowed = $type === 'event' ? self::EVENT_CAPABILITIES : self::TRIP_CAPABILITIES;
            if (array_diff($capabilities, $allowed) !== [] || $this->access->role($userId) === null) { throw new ValidationException(['capabilities' => 'invalid_capability_or_user']); }
            // Only global administrators can delegate rights they do not personally hold.
            if ($type !== 'default' && !$this->isAdmin() && array_diff($capabilities, $this->capabilities($type, $id)) !== []) {
                throw new AccessException('cannot_delegate');
            }
            // Event access.manage manages the independent template, not existing trips.
            $reason = $this->reason($reason);
            $this->access->replaceMember($type, $id, $userId, $capabilities);
            $this->audit($capabilities === [] ? 'access.revoke' : 'access.grant', $type, $id, $reason . ' · userId=' . $userId . ' · ' . implode(',', $capabilities));
        });
    }
    public function initializeTrip(int $id, ?int $eventId): void
    {
        $this->access->replaceMember('trip', $id, $this->actorId(), self::TRIP_CAPABILITIES);
        $this->audit('access.create', 'trip', $id, 'Reiseanlage: Ersteller');
        if ($eventId !== null) {
            foreach ($this->access->team('default', $eventId) as $member) {
                if ($member['userId'] === $this->actorId()) { continue; }
                $this->access->replaceMember('trip', $id, $member['userId'], $member['capabilities']);
                $this->audit('access.copy', 'trip', $id, 'Standardteam eventId=' . $eventId . ' · userId=' . $member['userId']);
            }
        }
    }
    public function initializeEvent(int $id): void
    {
        $this->access->replaceMember('event', $id, $this->actorId(), self::EVENT_CAPABILITIES);
        $this->audit('event.create', 'event', $id, 'Veranstaltung angelegt');
    }
    public function audit(string $action, string $type, int $id, string $reason): void
    {
        $this->access->audit($this->actorId(), $action, $type, $id, $reason);
    }
}
