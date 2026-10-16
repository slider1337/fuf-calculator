<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Data\RegistrationModeState;
use App\Application\Port\RegistrationModeRepositoryInterface;
use App\Domain\Trip\RegistrationMode;
use PDO;

final readonly class SqliteRegistrationModeRepository implements RegistrationModeRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function state(int $trip): ?RegistrationModeState
    {
        $statement = $this->pdo->prepare('SELECT registration_mode,registration_mode_revision FROM trips WHERE id=?');
        $statement->execute([$trip]);
        $value = $statement->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        return new RegistrationModeState(RegistrationMode::from($row->text('registration_mode')), $row->integer('registration_mode_revision'));
    }

    public function hasActiveOperations(int $trip): bool
    {
        foreach (['sync_jobs', 'term_update_jobs', 'participation_jobs'] as $table) {
            if ($this->exists("SELECT 1 FROM $table WHERE trip_id=? AND status IN ('pending','running','unknown','reconciling','resume_pending')", $trip)) { return true; }
        }
        return false;
    }

    public function hasManagedBookings(int $trip): bool
    {
        return $this->exists('SELECT 1 FROM registrations WHERE trip_id=? AND managed_booking=1', $trip);
    }

    public function hasApprovedGuests(int $trip): bool
    {
        return $this->exists("SELECT 1 FROM guest_approvals WHERE trip_id=? AND status='approved'", $trip);
    }

    public function disableMemberRegistration(int $trip): void
    {
        $this->pdo->prepare('UPDATE member_trip_settings SET enabled=0 WHERE trip_id=?')->execute([$trip]);
    }

    public function change(int $trip, RegistrationMode $mode): void
    {
        $this->pdo->prepare('UPDATE trips SET registration_mode=?,registration_mode_revision=registration_mode_revision+1 WHERE id=?')->execute([$mode->value, $trip]);
    }

    private function exists(string $sql, int $trip): bool
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$trip]);
        return $statement->fetchColumn() !== false;
    }
}
