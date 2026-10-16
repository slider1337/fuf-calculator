<?php

declare(strict_types=1);
namespace App\Application;

use App\Application\Port\{ExternalTermUpdatePort, TermRepositoryInterface, TermUpdateRepositoryInterface};

/** @phpstan-import-type FieldValues from TermFields
 * @phpstan-import-type JobSummary from TermUpdateRepositoryInterface
 * @phpstan-type Review array{externalId: string, before: FieldValues, changes: FieldValues, reviewHash: string, blockedReason: string|null}
 * @phpstan-type Receipt array{jobId: int, jobs: list<JobSummary>}
 */
final readonly class TermUpdateService
{
    public function __construct(private AccessPolicy $policy, private TermUpdateRepositoryInterface $store, private ExternalTermUpdatePort $external, private TermRepositoryInterface $terms) {}

    /** @return array{externalId: string, fields: FieldValues, blockedReason: string|null, jobs: list<JobSummary>} */
    public function read(int $trip): array
    {
        $this->policy->trip($trip, 'term.manage');
        $this->policy->requireRegistrationMode($trip, 'easyverein');
        $link = $this->terms->link($trip) ?? throw new AccessException('confirmed_term_required', 409);
        $fields = TermFields::observed($this->external->fields($link['externalId']));
        TermFields::period($fields);
        $this->policy->trip($trip, 'term.manage');
        $this->policy->requireRegistrationMode($trip, 'easyverein');
        if (($this->terms->link($trip)['externalId'] ?? null) !== $link['externalId']) { throw new AccessException('term_changed', 409); }
        return ['externalId' => $link['externalId'], 'fields' => $fields, 'blockedReason' => $this->external->blockedReason(), 'jobs' => $this->store->summaries($trip)];
    }

    /** @param array<array-key, mixed> $payload
     * @return Review
     */
    public function preview(int $trip, array $payload): array
    {
        $this->policy->trip($trip, 'term.manage', true);
        if (!is_array($payload['changes'] ?? null)) { throw new ValidationException(['changes' => 'object_required']); }
        $changes = TermFields::changes($payload['changes']);
        $current = $this->read($trip);
        TermFields::period(array_replace($current['fields'], $changes));
        $before = array_intersect_key($current['fields'], $changes);
        if (array_keys($before) !== array_keys($changes)) { throw new AccessException('provider_term_fields_unproven', 503); }
        if ($before === $changes) { throw new ValidationException(['changes' => 'changed_value_required']); }
        $review = ['externalId' => $current['externalId'], 'before' => $before, 'changes' => $changes];
        return $review + ['reviewHash' => PlanningTransferService::hash($review), 'blockedReason' => $current['blockedReason']];
    }

    /** @param array<array-key, mixed> $payload
     * @return Receipt
     */
    public function request(int $trip, array $payload): array
    {
        $this->policy->trip($trip, 'term.manage', true);
        $key = PlanningTransferService::operationKey($payload);
        $hash = PlanningTransferService::hash($payload);
        $old = $this->receipt($trip, $key, $hash);
        if ($old !== null) { return $old; }
        $review = $this->preview($trip, $payload); // Provider I/O stays outside the writer transaction.
        if (($payload['confirmed'] ?? null) !== true || !is_string($payload['reviewHash'] ?? null) || !hash_equals($review['reviewHash'], $payload['reviewHash'])) { throw new AccessException('term_review_changed', 409); }
        $reason = $this->policy->reason(is_string($payload['reason'] ?? null) ? $payload['reason'] : '');
        if ($review['blockedReason'] !== null) { throw new AccessException($review['blockedReason'], 409); }
        return $this->policy->writeTrip($trip, 'term.manage', function () use ($trip, $key, $hash, $review, $reason) {
            $old = $this->receipt($trip, $key, $hash);
            if ($old !== null) { return $old; }
            if (($this->terms->link($trip)['externalId'] ?? null) !== $review['externalId']) { throw new AccessException('term_changed', 409); }
            if ($this->store->hasActiveJob($trip)) { throw new AccessException('term_update_in_progress', 409); }
            $id = $this->store->request($trip, $review['externalId'], $this->policy->actorId(), $key, $hash, $review, $reason);
            $this->policy->audit('term.update_request', 'trip', $trip, $reason . ' · ' . implode(',', array_keys($review['changes'])));
            return ['jobId' => $id, 'jobs' => $this->store->summaries($trip)];
        });
    }

    /** @return Receipt|null */
    private function receipt(int $trip, string $key, string $hash): ?array
    {
        $old = $this->store->operation($trip, $key);
        if ($old === null) { return null; }
        if (!hash_equals($old['requestHash'], $hash)) { throw new AccessException('idempotency_conflict', 409); }
        return ['jobId' => $old['id'], 'jobs' => $this->store->summaries($trip)];
    }
}
