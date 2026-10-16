<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\RegistrationModeRepositoryInterface;
use App\Domain\Trip\RegistrationMode;

final readonly class RegistrationModeService
{
    public function __construct(private AccessPolicy $policy, private RegistrationModeRepositoryInterface $store) {}

    public static function validate(mixed $mode): string
    {
        if (!is_string($mode) || RegistrationMode::tryFrom($mode) === null) {
            throw new ValidationException(['registrationMode' => 'clubdesk_csv_or_easyverein']);
        }
        return $mode;
    }

    /** @return array{registrationMode: string, revision: int} */
    public function read(int $trip): array
    {
        $this->policy->trip($trip, 'trip.view');
        return ($this->store->state($trip) ?? throw new NotFoundException('Trip'))->toArray();
    }

    /** @param array<array-key, mixed> $payload
     * @return array{registrationMode: string, revision: int}
     */
    public function change(int $trip, array $payload): array
    {
        return $this->policy->writeTrip($trip, 'trip.manage', function () use ($trip, $payload) {
            $mode = RegistrationMode::from(self::validate($payload['registrationMode'] ?? null));
            $state = $this->store->state($trip) ?? throw new NotFoundException('Trip');
            if (($payload['revision'] ?? null) !== $state->revision) { throw new AccessException('revision_conflict', 409); }
            $reason = $this->policy->reason(is_string($payload['reason'] ?? null) ? $payload['reason'] : '');
            if (($payload['confirmed'] ?? null) !== true) { throw new ValidationException(['confirmed' => 'explicit_confirmation_required']); }
            if ($mode === $state->mode) { return $this->read($trip); }
            if ($this->store->hasActiveOperations($trip)) { throw new AccessException('registration_mode_operation_in_progress', 409); }
            if ($mode === RegistrationMode::ClubdeskCsv) {
                if ($this->store->hasManagedBookings($trip)) { throw new AccessException('registration_mode_managed_bookings_exist', 409); }
                if ($this->store->hasApprovedGuests($trip)) { throw new AccessException('registration_mode_guest_approvals_exist', 409); }
                $this->store->disableMemberRegistration($trip);
            }
            $this->store->change($trip, $mode);
            $this->policy->audit('trip.registration_mode', 'trip', $trip, $reason . ' · ' . $state->mode->value . ' → ' . $mode->value);
            return $this->read($trip);
        });
    }
}
