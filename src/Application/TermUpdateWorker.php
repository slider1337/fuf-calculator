<?php

declare(strict_types=1);
namespace App\Application;

use App\Application\Data\{TermUpdateJob, TermUpdateStatus};
use App\Application\Port\{AccessRepositoryInterface, ExternalTermUpdatePort, TermRepositoryInterface, TermUpdateRepositoryInterface};

/** A durable update receives at most one PATCH, even after timeout or lease expiry. */
final readonly class TermUpdateWorker
{
    public function __construct(private TermUpdateRepositoryInterface $store, private AccessRepositoryInterface $access, private ExternalTermUpdatePort $external, private TermRepositoryInterface $terms) {}

    public function runOne(?int $jobId = null): bool
    {
        if ($this->external->blockedReason() !== null) { return false; }
        $claimed = $this->access->transaction(function () use ($jobId) {
            $now = time();
            $this->store->expireLeases($now);
            $job = $this->store->nextReady($now, $jobId);
            if ($job === null) { return null; }
            if (!$this->allowed($job)) { $this->store->block($job->id, 'permission_revoked_or_trip_closed'); return false; }
            $token = bin2hex(random_bytes(16));
            $this->store->claim($job->id, $job->status === TermUpdateStatus::Pending, $token, $now + 300);
            return ['job' => $job, 'token' => $token];
        });
        if ($claimed === null) { return false; }
        if ($claimed === false) { return true; }
        $job = $claimed['job']; $token = $claimed['token'];
        $patch = $job->status === TermUpdateStatus::Pending;
        $status = TermUpdateStatus::Unknown; $error = 'external_result_unproven'; $patchStarted = false; $validationErrors = [];
        try {
            $current = TermFields::observed($this->external->fields($job->externalId));
            $selected = array_intersect_key($current, $job->changes);
            if ($selected === $job->changes) { $status = TermUpdateStatus::Succeeded; $error = null; }
            elseif ($patch) {
                if ($selected !== $job->before) { $status = TermUpdateStatus::Conflict; $error = 'term_review_changed'; }
                else {
                    TermFields::updatePeriod(array_replace($current, $job->changes));
                    $permitted = $this->access->transaction(fn () => $this->store->liveLeaseMatches($job->id, $token, time()) && $this->allowed($job));
                    if (!$permitted) { $status = TermUpdateStatus::Blocked; $error = 'permission_revoked_or_trip_closed'; }
                    else {
                        $patchStarted = true;
                        $this->external->patch($job->externalId, $job->changes);
                        $current = TermFields::observed($this->external->fields($job->externalId));
                        if (array_intersect_key($current, $job->changes) === $job->changes) { $status = TermUpdateStatus::Succeeded; $error = null; }
                    }
                }
            }
        } catch (ParticipationRejected $e) {
            $validationErrors = $e->validationErrors;
            // A definite HTTP rejection is not an ambiguous timeout.
            $status = TermUpdateStatus::Blocked;
            $error = match ($e->getCode()) {
                401 => 'term_update_unauthorized',
                403 => 'term_update_forbidden',
                404 => 'term_update_not_found',
                429 => 'provider_rate_limited',
                default => in_array(['field' => 'end_participation', 'reason' => 'overlap'], $e->validationErrors, true)
                    ? 'term_registration_overlap' : 'term_update_rejected',
            };
        } catch (AccessException $e) {
            $error = in_array($e->error, ['term_registration_overlap','provider_rate_limited','provider_read_failed','provider_read_invalid','provider_term_fields_unproven'], true) ? $e->error : ($patchStarted ? 'external_outcome_unknown' : 'provider_preflight_failed');
            if ($patch && !$patchStarted) { $status = TermUpdateStatus::Conflict; }
        } catch (\Throwable) {
            $error = $patchStarted ? 'external_outcome_unknown' : 'provider_preflight_failed';
            if ($patch && !$patchStarted) { $status = TermUpdateStatus::Conflict; }
        }
        $this->access->transaction(function () use ($job, $token, $status, $error, $validationErrors) {
            if (!$this->store->leaseMatches($job->id, $token)) { return; }
            $this->store->finish($job->id, $status, $error, $status === TermUpdateStatus::Unknown ? time() + 60 : null, $status === TermUpdateStatus::Succeeded ? gmdate(DATE_ATOM) : null, $validationErrors);
            if ($status === TermUpdateStatus::Succeeded && $this->allowed($job)) {
                $link = $this->terms->link($job->tripId) ?? throw new NotFoundException('Term link');
                $data = $link['data'];
                $fields = $data['termFields'] ?? [];
                if (!is_array($fields)) { throw new \UnexpectedValueException('Invalid stored term fields'); }
                $data['termFields'] = array_replace($fields, $job->changes);
                if (array_key_exists('registrationDeadline', $job->changes)) {
                    $deadline = $job->changes['registrationDeadline'];
                    $data['bookingUntil'] = is_string($deadline) ? (new \DateTimeImmutable($deadline))->getTimestamp() : PHP_INT_MAX;
                }
                if (isset($job->changes['capacity'])) { $data['capacity'] = $job->changes['capacity']; }
                $this->terms->updateLinkData($job->tripId, $data);
            }
            $this->access->audit($job->actorId, 'term.update_' . $status->value, 'trip', $job->tripId, $job->reason . ' · ' . implode(',', array_keys($job->changes)));
        });
        return true;
    }

    private function allowed(TermUpdateJob $job): bool
    {
        $metadata = $this->access->metadata($job->tripId);
        return ($metadata['registration_mode']) === 'easyverein'
            && ($metadata['archived_at'] ?? null) === null && ($metadata['closed_at'] ?? null) === null
            && in_array('term.manage', $this->access->capabilities('trip', $job->tripId, $job->actorId), true)
            && ($this->terms->link($job->tripId)['externalId'] ?? null) === $job->externalId;
    }
}
