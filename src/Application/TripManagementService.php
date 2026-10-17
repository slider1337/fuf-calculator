<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\TripRepositoryInterface;
use App\Domain\Service\PriceCalculatorService;

final readonly class TripManagementService
{
    public function __construct(private AccessPolicy $policy, private TripService $trips, private ActualExpenseService $expenses, private PriceCalculatorService $calculator,private TripRepositoryInterface $repository) {}
    public function close(int $id, string $reason): void
    {
        $this->policy->writeTrip($id, 'settlement.close', function () use ($id, $reason) {
            $reason = $this->policy->reason($reason);
            // Compute and freeze the final financial view before marking closed.
            $settlement = $this->expenses->getSettlement($id);
            unset($settlement['expenses']);
            $trip = $this->trips->getTrip($id);
            $calculation = $this->calculator->calculate($trip);
            $this->policy->setMetadata($id, ['closed_at' => gmdate(DATE_ATOM), 'closed_by' => $this->policy->actorId(),
                'settlement_snapshot' => json_encode($settlement, JSON_THROW_ON_ERROR), 'calculation_snapshot' => json_encode($calculation, JSON_THROW_ON_ERROR)]);
            $this->policy->audit('settlement.close', 'trip', $id, $reason);
        });
    }
    public function reopen(int $id, string $reason): void
    {
        $this->policy->transaction(function () use ($id, $reason) {
            $this->policy->trip($id, 'settlement.reopen');
            $reason = $this->policy->reason($reason);
            if (($this->policy->metadata($id)['closed_at'] ?? null) === null) { throw new AccessException('trip_not_closed', 409); }
            $this->policy->setMetadata($id, ['closed_at' => null, 'closed_by' => null, 'settlement_snapshot' => null, 'calculation_snapshot' => null]);
            $this->policy->audit('settlement.reopen', 'trip', $id, $reason);
        });
    }
    /**
     * @param array<array-key,mixed> $p
     * @return array<array-key,mixed>
     */
    public function archive(int $id, array $p): array
    {
        return $this->policy->transaction(function () use ($id, $p) {
            $this->policy->trip($id, 'trip.manage');
            if (!is_bool($p['archived'] ?? null)) { throw new ValidationException(['archived'=>'boolean_required']); }
            $metadata = $this->policy->metadata($id);
            if($p['archived']===(($metadata['archived_at']??null)!==null)){return ['archivedAt'=>$metadata['archived_at']??null];}
            if (!array_key_exists('archivedAt', $p) || $p['archivedAt'] !== ($metadata['archived_at'] ?? null)) { throw new AccessException('revision_conflict',409); }
            $reason = $p['archived']?'Reise archiviert':'Reise aus Archiv geholt';
            $archivedAt = $p['archived'] ? (($metadata['archived_at'] ?? null) ?? gmdate(DATE_ATOM)) : null;
            $this->policy->setMetadata($id, ['archived_at'=>$archivedAt]);
            $this->policy->audit($p['archived']?'trip.archive':'trip.restore','trip',$id,$reason);
            return ['archivedAt'=>$archivedAt];
        });
    }
    /** @param array<array-key,mixed> $payload
     * @return array{deleted:bool} */
    public function remove(int $id,array $payload): array
    {
        return $this->policy->transaction(function()use($id,$payload){
            $this->policy->trip($id,'trip.manage');
            if(($payload['confirmed']??null)!==true)throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);
            $this->repository->remove($id);
            $this->policy->audit('trip.delete','trip',$id,'Reise nur im Calculator gelöscht; Historie erhalten');
            return ['deleted'=>true];
        });
    }
    /**
     * @param array<array-key,mixed> $payload
     * @return array<array-key,mixed>
     */
    public function duplicate(int $id, array $payload): array
    {
        return $this->policy->transaction(function () use ($id, $payload) {
            $this->policy->trip($id, 'trip.manage');
            $this->policy->trip($id, 'calculation.manage');
            $trip = $this->trips->getTrip($id);
            if (!isset($payload['name'], $payload['startDate'])) { throw new ValidationException(['name' => 'required', 'startDate' => 'required']); }
            $copy = TripData::fromTrip($trip);
            unset($copy['id']);
            $copy['eventId'] = $trip->eventId();
            $copy['registrationMode']=$payload['registrationMode']??$this->policy->metadata($id)['registration_mode'];
            // Explicit dates/name, existing planning only. Creator + current template;
            // never copy members, participants, closure, invoices or external references.
            $copy['name'] = $payload['name']; $copy['startDate'] = $payload['startDate']; $copy['endDate'] = $payload['endDate'] ?? null;
            $created = $this->trips->createFromArray($copy);
            $this->policy->audit('trip.duplicate', 'trip', ($created->id() ?? throw new \LogicException('Saved trip missing ID')), 'Planungsvorlage tripId=' . $id);
            return ['id' => $created->id()];
        });
    }
    /** @param array<array-key,mixed> $pairs */
    public function legacyImport(array $pairs, string $reason): void
    {
        $this->policy->admin();
        $this->policy->transaction(function () use ($pairs, $reason) {
            $reason = $this->policy->reason($reason);
            $candidates = [];
            foreach ($this->policy->candidates() as $row) { $candidates[$row['tripId'] . ':' . $row['userId']] = true; }
            foreach (Input::objects($pairs) as $pair) {
                $trip = (int) Input::scalar($pair['tripId'] ?? 0); $user = (int) Input::scalar($pair['userId'] ?? 0);
                if (!isset($candidates[$trip . ':' . $user])) { throw new ValidationException(['pairs' => 'not_in_migration_snapshot']); }
                $this->policy->grant('trip', $trip, $user, Input::strings($pair['capabilities'] ?? AccessPolicy::TRIP_CAPABILITIES), $reason);
                $this->policy->audit('access.legacy', 'trip', $trip, $reason . ' · userId=' . $user);
            }
        });
    }
}
