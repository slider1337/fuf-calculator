<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\Port\ParticipationRepositoryInterface;
use PDO;

/**
 * @phpstan-import-type LocalPerson from ParticipationRepositoryInterface
 * @phpstan-import-type ParticipationJob from \App\Application\Port\ParticipationRepositoryInterface
 */
final readonly class SqliteParticipationRepository implements ParticipationRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    /** @param list<mixed> $params */
    private function execute(string $sql,array $params): \PDOStatement { foreach($params as $value) { if($value!==null&&!is_scalar($value))throw new \UnexpectedValueException("Invalid database parameter"); } $s=$this->pdo->prepare($sql); $s->execute($params); return $s; }
    public function applyPersonResult(mixed $external, mixed $status, mixed $priceGroup, mixed $price, mixed $booking, mixed $person): void
    {
        $s=$this->execute("UPDATE registration_participants SET external_reference=?,participation_status=?,external_price_group=?,external_price=?,sync_status='synced' WHERE registration_id=? AND id=?", [$external, $status, $priceGroup, $price, $booking, $person]);
    }
    /** @return array<string,scalar|null>|false */
    public function bookingRevision(mixed $booking): array|false
    {
        $s=$this->execute('SELECT trip_id,revision FROM registrations WHERE id=?', [$booking]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function advanceBookingRevision(mixed $booking): void
    {
        $s=$this->execute('UPDATE registrations SET revision=revision+1 WHERE id=?', [$booking]);
    }
    public function bookingPersonStatus(mixed $booking, mixed $person): int|string|false|null
    {
        $s=$this->execute('SELECT participation_status FROM registration_participants WHERE registration_id=? AND id=?', [$booking, $person]);
        return $s->fetchColumn();
    }
    public function setObservedPrice(mixed $priceGroup, mixed $price, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET external_price_group=?,external_price=? WHERE id=?', [$priceGroup, $price, $person]);
    }
    public function acceptPersonPrice(mixed $person, mixed $hash, mixed $actor, mixed $reason, mixed $createdAt): void
    {
        $s=$this->execute('INSERT INTO accepted_person_observations (participant_id,external_hash,actor_id,reason,created_at) VALUES (?,?,?,?,?) ON CONFLICT(participant_id) DO UPDATE SET external_hash=excluded.external_hash,actor_id=excluded.actor_id,reason=excluded.reason,created_at=excluded.created_at', [$person, $hash, $actor, $reason, $createdAt]);
    }
    public function recordPersonHistory(mixed $person, mixed $data, mixed $actor, mixed $reason, mixed $createdAt): void
    {
        $s=$this->execute('INSERT INTO person_observation_history (participant_id,data_json,actor_id,reason,created_at) VALUES (?,?,?,?,?)', [$person, $data, $actor, $reason, $createdAt]);
    }
    public function resolveConflicts(mixed $conflicts, mixed $trip): void
    {
        $s=$this->execute('UPDATE external_observations SET revision=revision+1,conflicts_json=? WHERE trip_id=?', [$conflicts, $trip]);
    }
    /** @return array<string,scalar|null>|false */
    public function personInTrip(mixed $person, mixed $trip): array|false
    {
        $s=$this->execute('SELECT p.*,r.trip_id FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE p.id=? AND r.trip_id=?', [$person, $trip]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    /** @return array<string,scalar|null>|false */
    public function observation(mixed $trip): array|false
    {
        $s=$this->execute('SELECT * FROM external_observations WHERE trip_id=?', [$trip]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function assignClarification(mixed $caseId): void
    {
        $s=$this->execute("UPDATE external_clarifications SET status='assigned' WHERE id=?", [$caseId]);
    }
    public function completePersonJobs(mixed $person, mixed $kind): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status='succeeded',error_code=NULL,lease_token=NULL,lease_until=NULL WHERE participant_id=? AND status IN ('pending','unknown','blocked') AND kind=?", [$person, $kind]);
    }
    public function setPersonMember(mixed $member, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET member_reference=? WHERE id=?', [$member, $person]);
    }
    public function runningPersonJob(mixed $person): int|string|false|null
    {
        $s=$this->execute("SELECT 1 FROM participation_jobs WHERE participant_id=? AND status IN ('running','reconciling')", [$person]);
        return $s->fetchColumn();
    }
    public function duplicateExternal(mixed $external, mixed $person): int|string|false|null
    {
        $s=$this->execute('SELECT 1 FROM registration_participants WHERE external_reference=? AND id<>?', [$external, $person]);
        return $s->fetchColumn();
    }
    /** @return array<string,scalar|null>|false */
    public function unassignedClarification(mixed $trip, mixed $external): array|false
    {
        $s=$this->execute("SELECT * FROM external_clarifications WHERE trip_id=? AND external_id=? AND status='unassigned'", [$trip, $external]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function saveLinkData(mixed $data, mixed $trip): void
    {
        $s=$this->execute('UPDATE external_event_links SET external_json=? WHERE trip_id=?', [$data, $trip]);
    }
    public function saveObservation(mixed $trip, mixed $revision, mixed $snapshot, mixed $conflicts, mixed $observedAt): void
    {
        $s=$this->execute('INSERT INTO external_observations (trip_id,revision,snapshot_json,conflicts_json,observed_at) VALUES (?,?,?,?,?) ON CONFLICT(trip_id) DO UPDATE SET revision=excluded.revision,snapshot_json=excluded.snapshot_json,conflicts_json=excluded.conflicts_json,observed_at=excluded.observed_at,cache_invalidated=0', [$trip, $revision, $snapshot, $conflicts, $observedAt]);
    }
    public function acceptedPriceHash(mixed $person): int|string|false|null
    {
        $s=$this->execute('SELECT external_hash FROM accepted_person_observations WHERE participant_id=?', [$person]);
        return $s->fetchColumn();
    }
    public function completeClarification(mixed $trip, mixed $external): void
    {
        $s=$this->execute("UPDATE external_clarifications SET status='imported' WHERE trip_id=? AND external_id=?", [$trip, $external]);
    }
    public function saveClarification(mixed $trip, mixed $external, mixed $data, mixed $observedAt): void
    {
        $s=$this->execute('INSERT INTO external_clarifications (trip_id,external_id,data_json,observed_at) VALUES (?,?,?,?) ON CONFLICT(trip_id,external_id) DO UPDATE SET data_json=excluded.data_json,observed_at=excluded.observed_at', [$trip, $external, $data, $observedAt]);
    }
    public function markImported(mixed $data, mixed $observedAt, mixed $trip, mixed $external): void
    {
        $s=$this->execute("UPDATE external_clarifications SET status='imported',data_json=?,observed_at=? WHERE trip_id=? AND external_id=?", [$data, $observedAt, $trip, $external]);
    }
    /** @return list<array<string,scalar|null>> */
    public function peopleByExternal(mixed $external, mixed $trip): array
    {
        $s=$this->execute('SELECT p.*,r.trip_id,r.source FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE p.external_reference=? AND r.trip_id=?', [$external, $trip]);
        $rows=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $rows[]=StoredRow::scalars($row); } return $rows;
    }
    public function observationRevision(mixed $trip): int|string|false|null
    {
        $s=$this->execute('SELECT revision FROM external_observations WHERE trip_id=?', [$trip]);
        return $s->fetchColumn();
    }
    public function markPending(mixed $person): void
    {
        $s=$this->execute("UPDATE registration_participants SET sync_status='pending' WHERE id=?", [$person]);
    }
    public function retryJob(mixed $actor, mixed $job): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status='pending',error_code=NULL,lease_until=NULL,actor_id=?,member_actor=NULL WHERE id=?", [$actor, $job]);
    }
    public function personStatus(mixed $person): int|string|false|null
    {
        $s=$this->execute('SELECT participation_status FROM registration_participants WHERE id=?', [$person]);
        return $s->fetchColumn();
    }
    /** @return ParticipationJob|false */
    public function job(mixed $job, mixed $trip): array|false
    {
        $s=$this->execute('SELECT * FROM participation_jobs WHERE id=? AND trip_id=?', [$job, $trip]);
        $row=StoredRow::fetch($s); if($row===false)return false; return self::jobRow($row);
    }
    public function setBookingState(mixed $status, mixed $sync, mixed $booking): void
    {
        $s=$this->execute('UPDATE registrations SET booking_status=?,sync_status=? WHERE id=?', [$status, $sync, $booking]);
    }
    /** @return list<array{participation_status:string,sync_status:string}> */
    public function personStates(mixed $booking): array
    {
        $s=$this->execute('SELECT participation_status,sync_status FROM registration_participants WHERE registration_id=?', [$booking]);
        $rows=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $r=StoredRow::from($row); $rows[]=["participation_status"=>$r->text("participation_status"),"sync_status"=>$r->text("sync_status")]; } return $rows;
    }
    public function markPersonPending(mixed $member, mixed $priceGroup, mixed $price, mixed $status, mixed $booking, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET member_reference=?,price_group_reference=?,expected_price=?,sync_status=? WHERE registration_id=? AND id=?', [$member, $priceGroup, $price, $status, $booking, $person]);
    }
    public function enqueue(mixed $trip, mixed $booking, mixed $person, mixed $kind, mixed $key, mixed $hash, mixed $payload, mixed $actor, mixed $member, mixed $status, mixed $error, mixed $createdAt): void
    {
        $s=$this->execute('INSERT INTO participation_jobs (trip_id,registration_id,participant_id,kind,operation_key,request_hash,payload_json,actor_id,member_actor,status,error_code,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$trip, $booking, $person, $kind, $key, $hash, $payload, $actor, $member, $status, $error, $createdAt]);
    }
    public function activePersonJob(mixed $person): int|string|false|null
    {
        $s=$this->execute("SELECT kind FROM participation_jobs WHERE participant_id=? AND status IN ('pending','running','unknown','reconciling')", [$person]);
        return $s->fetchColumn();
    }
    /** @return list<mixed> */
    public function operationHashes(mixed $trip, mixed $key): array
    {
        $s=$this->execute('SELECT request_hash FROM participation_jobs WHERE trip_id=? AND operation_key=?', [$trip, $key]); return array_values($s->fetchAll(PDO::FETCH_COLUMN));
    }
    /** @return list<array<string,scalar|null>> */
    public function clarifications(mixed $trip): array
    {
        $s=$this->execute("SELECT external_id AS externalId,status,observed_at AS observedAt FROM external_clarifications WHERE trip_id=? AND status<>'imported' ORDER BY id DESC LIMIT 100", [$trip]);
        $rows=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $rows[]=StoredRow::scalars($row); } return $rows;
    }
    /** @return list<array<string,scalar|null>> */
    public function recentJobs(mixed $trip): array
    {
        $s=$this->execute('SELECT j.id,j.registration_id AS bookingId,j.participant_id AS personId,p.name AS personName,CASE WHEN p.guest_approval_id IS NULL THEN 0 ELSE 1 END AS isGuest,j.kind,j.status,j.attempts,j.error_code AS errorCode,j.initial_error_code AS initialErrorCode,j.validation_errors_json AS validationErrorsJson,j.lease_until AS nextAttemptAt,j.created_at AS createdAt FROM participation_jobs j JOIN registration_participants p ON p.id=j.participant_id AND p.registration_id=j.registration_id JOIN registrations r ON r.id=j.registration_id AND r.trip_id=j.trip_id WHERE j.trip_id=? ORDER BY j.id DESC LIMIT 100', [$trip]);
        $rows=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $rows[]=StoredRow::scalars($row); } return $rows;
    }
    public function markUnknown(mixed $person): void
    {
        $s=$this->execute("UPDATE registration_participants SET sync_status='unknown' WHERE id=?", [$person]);
    }
    public function blockJob(mixed $job): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status='unknown',lease_token=NULL,lease_until=strftime('%s','now')+300,error_code='permission_revoked_or_trip_closed' WHERE id=?", [$job]);
    }
    public function isImportedPerson(int $trip,int $booking,int $person): bool
    {
        return $this->execute("SELECT 1 FROM registrations r JOIN registration_participants p ON p.registration_id=r.id WHERE r.trip_id=? AND r.id=? AND p.id=? AND r.source='easyverein' AND p.member_reference IS NOT NULL AND p.external_reference IS NOT NULL",[$trip,$booking,$person])->fetchColumn()!==false;
    }
    public function registrationOwner(mixed $booking, mixed $trip): int|string|false|null
    {
        $s=$this->execute('SELECT owner_member_reference FROM registrations WHERE id=? AND trip_id=?', [$booking, $trip]);
        return $s->fetchColumn();
    }
    /** @return array<string,scalar|null>|false */
    public function memberSettings(mixed $trip): array|false
    {
        $s=$this->execute('SELECT * FROM member_trip_settings WHERE trip_id=?', [$trip]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function setPersonSync(mixed $sync, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET sync_status=? WHERE id=?', [$sync, $person]);
    }
    /** @param list<array{field:string,reason:string}> $validationErrors */
    public function deferJob(mixed $status, mixed $until, mixed $error, mixed $job,array $validationErrors=[]): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status=?,lease_token=NULL,lease_until=?,error_code=?,initial_error_code=COALESCE(initial_error_code,?),validation_errors_json=CASE WHEN ?='[]' THEN validation_errors_json ELSE ? END WHERE id=?", [$status, $until, $error,$error,json_encode($validationErrors,JSON_THROW_ON_ERROR),json_encode($validationErrors,JSON_THROW_ON_ERROR), $job]);
    }
    public function completeJob(mixed $job): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status='succeeded',lease_token=NULL,lease_until=NULL,error_code=NULL WHERE id=?", [$job]);
    }
    public function duplicateExternalPerson(mixed $external, mixed $person): int|string|false|null
    {
        $s=$this->execute('SELECT id FROM registration_participants WHERE external_reference=? AND id<>?', [$external, $person]);
        return $s->fetchColumn();
    }
    public function leaseToken(mixed $job): int|string|false|null
    {
        $s=$this->execute('SELECT lease_token FROM participation_jobs WHERE id=?', [$job]);
        return $s->fetchColumn();
    }
    /** @return array<string,scalar|null>|false */
    public function guestForJob(mixed $guest, mixed $trip): array|false
    {
        $s=$this->execute("SELECT participant_id,status,parent_member FROM guest_approvals WHERE id=? AND trip_id=?", [$guest, $trip]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function claimJob(mixed $status, mixed $token, mixed $until, mixed $job): void
    {
        $s=$this->execute('UPDATE participation_jobs SET status=?,lease_token=?,lease_until=?,attempts=attempts+1 WHERE id=?', [$status, $token, $until, $job]);
    }
    /** @return ParticipationJob|false */
    public function nextJob(mixed $now): array|false
    {
        $s=$this->execute("SELECT * FROM participation_jobs WHERE status IN ('pending','unknown') AND (lease_until IS NULL OR lease_until<=?) ORDER BY id LIMIT 1", [$now]);
        $row=StoredRow::fetch($s); if($row===false)return false; return self::jobRow($row);
    }
    public function invalidateObservation(int $trip): void {$this->execute("UPDATE external_observations SET cache_invalidated=1 WHERE trip_id=?",[$trip]);}
    public function pendingFamilyJobs(int $booking,string $kind,int $now): array
    {
        $s=$this->execute("SELECT * FROM participation_jobs WHERE registration_id=? AND kind=? AND status='pending' AND (lease_until IS NULL OR lease_until<=?) AND json_extract(payload_json,'$.guestId') IS NULL AND participant_id IN (SELECT id FROM registration_participants WHERE guest_approval_id IS NULL) ORDER BY id LIMIT 25",[$booking,$kind,$now]);
        return array_map(self::jobRow(...),StoredRow::fetchAll($s));
    }
    public function expireLeases(mixed $now): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?", [$now]);
    }
    public function recordImportedHistory(mixed $person, mixed $data, mixed $actor, mixed $reason, mixed $createdAt): void
    {
        $s=$this->execute('INSERT INTO person_observation_history(participant_id,data_json,actor_id,reason,created_at) VALUES (?,?,?,?,?)', [$person, $data, $actor, $reason, $createdAt]);
    }
    public function syncImportedPerson(mixed $status, mixed $priceGroup, mixed $price, mixed $person): void
    {
        $s=$this->execute("UPDATE registration_participants SET participation_status=?,external_price_group=?,external_price=?,sync_status='synced' WHERE id=?", [$status, $priceGroup, $price, $person]);
    }
    /** @return array<string,scalar|null>|false */
    public function importedProfile(mixed $person): array|false
    {
        $s=$this->execute('SELECT name,birth_date,participation_status,external_price_group,external_price FROM registration_participants WHERE id=?', [$person]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function setImportedIdentity(mixed $member, mixed $external, mixed $priceGroup, mixed $price, mixed $guest, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET member_reference=?,external_reference=?,price_group_reference=?,expected_price=?,guest_approval_id=? WHERE id=?', [$member, $external, $priceGroup, $price, $guest, $person]);
    }
    public function createImportedGuest(mixed $trip, mixed $owner, mixed $parent, mixed $name, mixed $birthDate, mixed $priceGroup, mixed $price, mixed $actor, mixed $createdAt, mixed $reason, mixed $operationKey, mixed $requestHash, mixed $person): int
    {
        $s=$this->execute('INSERT INTO guest_approvals(trip_id,owner_member,parent_member,name,birth_date,price_group,price,actor_id,created_at,reason,operation_key,request_hash,participant_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [$trip, $owner, $parent, $name, $birthDate, $priceGroup, $price, $actor, $createdAt, $reason, $operationKey, $requestHash, $person]);
        return StoredRow::from(["id"=>$this->pdo->lastInsertId()])->integer("id");
    }
    public function importedPersonId(mixed $booking): int|string|false|null
    {
        $s=$this->execute('SELECT id FROM registration_participants WHERE registration_id=?', [$booking]);
        return $s->fetchColumn();
    }
    public function markProviderBooking(mixed $reference, mixed $booking): void
    {
        $s=$this->execute("UPDATE registrations SET source='easyverein',reference=? WHERE id=?", [$reference, $booking]);
    }
    public function completeRegistrationJobs(mixed $person): void
    {
        $s=$this->execute("UPDATE participation_jobs SET status='succeeded',error_code=NULL,lease_token=NULL,lease_until=NULL WHERE participant_id=? AND kind='register' AND status IN ('pending','unknown','blocked')", [$person]);
    }
    public function setExternalReference(mixed $external, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET external_reference=? WHERE id=?', [$external, $person]);
    }
    public function recordScheduledAttempt(mixed $trip, mixed $now): void
    {
        $s=$this->execute('INSERT INTO reconciliation_schedule (trip_id,attempted_at) VALUES (?,?) ON CONFLICT(trip_id) DO UPDATE SET attempted_at=excluded.attempted_at', [$trip, $now]);
    }
    public function nextScheduledTrip(mixed $actor, mixed $before): int|string|false|null
    {
        $s=$this->execute("SELECT l.trip_id FROM external_event_links l JOIN trips t ON t.id=l.trip_id JOIN trip_access a ON a.trip_id=l.trip_id LEFT JOIN reconciliation_schedule s ON s.trip_id=l.trip_id WHERE a.user_id=? AND a.capability='term.manage' AND t.registration_mode='easyverein' AND t.archived_at IS NULL AND (s.attempted_at IS NULL OR s.attempted_at<=?) ORDER BY COALESCE(s.attempted_at,0),l.trip_id LIMIT 1", [$actor, $before]);
        return $s->fetchColumn();
    }
    /** @return array{externalId:string,confirmedAt:string,data:array<array-key,mixed>}|null */
    public function link(int $trip): ?array { return (new SqliteTermRepository($this->pdo))->link($trip); }
    /** @return list<LocalPerson> */
    public function localPeopleByExternal(int $trip,string $external): array { return $this->localPeople('r.trip_id=? AND p.external_reference=?',[$trip,$external]); }
    /** @return list<LocalPerson> */
    public function localPeopleById(int $person,int $trip): array { return $this->localPeople('p.id=? AND r.trip_id=?',[$person,$trip]); }
    /** @return list<LocalPerson> */
    public function unboundLocalMembers(int $trip,string $member): array { return $this->localPeople('r.trip_id=? AND p.member_reference=? AND p.external_reference IS NULL AND p.guest_approval_id IS NULL AND r.managed_booking=1',[$trip,$member]); }
    /**
     * @param list<int|string|null> $params
     * @return list<LocalPerson>
     */
    private function localPeople(string $where,array $params): array
    {
        $result=[];
        foreach($this->execute('SELECT p.*,r.source FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE '.$where,$params)->fetchAll(\PDO::FETCH_ASSOC) as $row){
            if(!is_array($row)||!is_int($row['id']??null)||!is_int($row['registration_id']??null)||!is_string($row['source']??null))throw new \LogicException('Invalid local person');
            foreach(['member_reference','external_reference','price_group_reference','expected_price'] as $key)if(!array_key_exists($key,$row)||($row[$key]!==null&&!is_string($row[$key])))throw new \LogicException('Invalid local reference');
            if(!array_key_exists('guest_approval_id',$row)||($row['guest_approval_id']!==null&&!is_int($row['guest_approval_id'])))throw new \LogicException('Invalid local guest');
            $result[]=['id'=>$row['id'],'registration_id'=>$row['registration_id'],'source'=>$row['source'],'member_reference'=>$this->nullableReference($row,'member_reference'),'external_reference'=>$this->nullableReference($row,'external_reference'),'price_group_reference'=>$this->nullableReference($row,'price_group_reference'),'expected_price'=>$this->nullableReference($row,'expected_price'),'guest_approval_id'=>$row['guest_approval_id']];
        }
        return $result;
    }

    /** @param array<array-key,mixed> $row */
    private function nullableReference(array $row,string $key): ?string
    {
        if(!array_key_exists($key,$row)||($row[$key]!==null&&!is_string($row[$key])))throw new \LogicException('Invalid local reference');
        return $row[$key];
    }

    /**
     * @param array<string,scalar|null> $value
     * @return ParticipationJob
     */
    private static function jobRow(array $value): array
    {
        $r=StoredRow::from($value);
        return ['id'=>$r->integer('id'),'trip_id'=>$r->integer('trip_id'),'registration_id'=>$r->integer('registration_id'),
            'participant_id'=>$r->integer('participant_id'),'actor_id'=>$r->integer('actor_id'),'kind'=>$r->text('kind'),
            'status'=>$r->text('status'),'attempts'=>$r->integer('attempts'),'member_actor'=>$r->nullableText('member_actor'),
            'payload_json'=>$r->text('payload_json')]+$value;
    }
}
