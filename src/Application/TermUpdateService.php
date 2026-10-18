<?php

declare(strict_types=1);
namespace App\Application;

use App\Application\Port\{ExternalTermUpdatePort, TermRepositoryInterface, TermUpdateRepositoryInterface, TripRepositoryInterface};

/** @phpstan-import-type FieldValues from TermFields
 * @phpstan-import-type JobSummary from TermUpdateRepositoryInterface
 * @phpstan-type Review array{externalId: string, before: FieldValues, changes: FieldValues, reviewHash: string, blockedReason: string|null}
 * @phpstan-type Receipt array{jobId: int, jobs: list<JobSummary>}
 */
final readonly class TermUpdateService
{
    public function __construct(private AccessPolicy $policy, private TermUpdateRepositoryInterface $store, private ExternalTermUpdatePort $external, private TermRepositoryInterface $terms, private TripRepositoryInterface $trips, private TermUpdateWorker $worker, private ?\App\Application\Port\EasyVereinReadClient $displayClient=null) {}

    /** @return array{externalId: string, fields: FieldValues, blockedReason: string|null, jobs: list<JobSummary>, observedHash: string, tripFields: FieldValues} */
    public function read(int $trip): array
    {
        $this->policy->trip($trip, 'term.manage');
        $this->policy->requireRegistrationMode($trip, 'easyverein');
        $link = $this->terms->link($trip) ?? throw new AccessException('confirmed_term_required', 409);
        $fields = $this->displayClient === null ? TermFields::observed($this->external->fields($link['externalId'])) : \App\Infrastructure\EasyVereinTermFields::read($this->displayClient->get('event/'.$link['externalId'].'/'));
        TermFields::period($fields);
        $this->policy->trip($trip, 'term.manage');
        $this->policy->requireRegistrationMode($trip, 'easyverein');
        if (($this->terms->link($trip)['externalId'] ?? null) !== $link['externalId']) { throw new AccessException('term_changed', 409); }
        $tripFields = $this->tripDates($trip);
        return ['tripFields' => $tripFields, 'observedHash' => PlanningTransferService::hash([$trip, $link['externalId'], $fields, $tripFields]), 'externalId' => $link['externalId'], 'fields' => $fields, 'blockedReason' => $this->external->blockedReason(), 'jobs' => $this->store->summaries($trip)];
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
        TermFields::updatePeriod(array_replace($current['fields'], $changes));
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
        $reason = 'Termin im Calculator gespeichert';
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

    /** One user action: validate, enqueue durably, execute this exact job and return its outcome.
     * @param array<array-key, mixed> $payload
     * @return array{jobId:?int,status:string,errorCode:?string,validationErrors:list<array{field:string,reason:string}>}
     */
    public function save(int $trip, array $payload): array
    {
        $this->policy->trip($trip, 'term.manage', true);
        $key = PlanningTransferService::operationKey($payload);
        $hash = PlanningTransferService::hash($payload);
        $old = $this->receipt($trip, $key, $hash);
        if ($old !== null) { return $this->execute($trip, $old['jobId']); }
        $current = $this->read($trip);
        if (!is_string($payload['observedHash'] ?? null) || !hash_equals($current['observedHash'], $payload['observedHash'])) {
            throw new AccessException('term_review_changed', 409);
        }
        if ($current['blockedReason'] !== null) { throw new AccessException($current['blockedReason'], 409); }
        if (!is_array($payload['changes'] ?? null)) { throw new ValidationException(['changes'=>'object_required']); }
        // datetime-local values are interpreted in the club's timezone, not the device timezone.
        $input = $payload['changes'];
        $deadline = $input['registrationDeadline'] ?? null;
        if (is_string($deadline) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/D', $deadline)) {
            $local = strlen($deadline) === 16 ? $deadline . ':00' : $deadline;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $local, new \DateTimeZone('Europe/Berlin'));
            if ($parsed === false || $parsed->format('Y-m-d\TH:i:s') !== $local) { throw new ValidationException(['changes.registrationDeadline'=>'invalid_term_value']); }
            $input['registrationDeadline'] = $parsed->format(DATE_ATOM);
        }
        // Dates always come from the stored trip, even if a client submits other dates.
        $changes = TermFields::changes(array_replace($input, $current['tripFields']));
        TermFields::updatePeriod(array_replace($current['fields'], $changes));
        $changes = array_filter($changes, static fn ($value, $field) => !array_key_exists($field, $current['fields']) || $value !== $current['fields'][$field], ARRAY_FILTER_USE_BOTH);
        if (!$changes) { return ['jobId'=>null, 'status'=>'succeeded', 'errorCode'=>null, 'validationErrors'=>[]]; }
        $before = array_intersect_key($current['fields'], $changes);
        if (array_keys($before) !== array_keys($changes)) { throw new AccessException('provider_term_fields_unproven', 503); }
        $id = $this->policy->writeTrip($trip, 'term.manage', function () use ($trip, $key, $hash, $current, $before, $changes) {
            $old = $this->receipt($trip, $key, $hash);
            if ($old !== null) { return $old['jobId']; }
            if (($this->terms->link($trip)['externalId'] ?? null) !== $current['externalId'] || $this->tripDates($trip) !== $current['tripFields']) {
                throw new AccessException('term_review_changed', 409);
            }
            if ($this->store->hasActiveJob($trip)) { throw new AccessException('term_update_in_progress', 409); }
            $review = ['externalId'=>$current['externalId'], 'before'=>$before, 'changes'=>$changes];
            $id = $this->store->request($trip, $current['externalId'], $this->policy->actorId(), $key, $hash, $review, 'Termin gespeichert');
            $this->policy->audit('term.update_request', 'trip', $trip, implode(',', array_keys($changes)));
            return $id;
        });
        return $this->execute($trip, $id);
    }

    /** @return FieldValues */
    private function tripDates(int $id): array
    {
        $trip = $this->trips->getById($id) ?? throw new NotFoundException('Trip');
        $tz = new \DateTimeZone('Europe/Berlin');
        $start = new \DateTimeImmutable($trip->startDate()->format('Y-m-d') . ' 00:00:00', $tz);
        $end = new \DateTimeImmutable(($trip->endDate() ?? $trip->startDate())->format('Y-m-d') . ' 23:59:59', $tz);
        return TermFields::observed(['start'=>$start->format(DATE_ATOM), 'end'=>$end->format(DATE_ATOM)]);
    }

    /** @return array{jobId:?int,status:string,errorCode:?string,validationErrors:list<array{field:string,reason:string}>} */
    private function execute(int $trip, int $jobId): array
    {
        $this->worker->runOne($jobId);
        foreach ($this->store->summaries($trip) as $job) {
            if ($job['id'] === $jobId) { return ['jobId'=>$jobId, 'status'=>$job['status'], 'errorCode'=>$job['errorCode'], 'validationErrors'=>$job['validationErrors']]; }
        }
        throw new NotFoundException('Term update');
    }

    /** Accept a freshly reviewed external stand and close an uncertain job locally.
     * No PATCH is repeated and no claim is made about the original outcome.
     * @param array<array-key, mixed> $payload
     * @return Receipt
     */
    public function resolve(int $trip, array $payload): array
    {
        $this->policy->trip($trip, 'term.manage', true);
        $current = $this->read($trip);
        if (($payload['confirmed'] ?? null) !== true || !is_int($payload['jobId'] ?? null)
            || !is_string($payload['observedHash'] ?? null) || !hash_equals($current['observedHash'], $payload['observedHash'])) {
            throw new AccessException('term_review_changed', 409);
        }
        $reason = 'Aktuellen easyVerein-Stand übernommen';
        return $this->policy->writeTrip($trip, 'term.manage', function () use ($trip, $payload, $current, $reason) {
            $this->policy->requireRegistrationMode($trip, 'easyverein');
            if (($this->terms->link($trip)['externalId'] ?? null) !== $current['externalId']
                || !$this->store->resolveUnknown($trip, $payload['jobId'])) {
                throw new AccessException('term_review_changed', 409);
            }
            $this->policy->audit('term.update_resolved', 'trip', $trip, $reason . ' · Auftrag #' . $payload['jobId'] . ' · ' . $current['observedHash']);
            return ['jobId' => $payload['jobId'], 'jobs' => $this->store->summaries($trip)];
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
