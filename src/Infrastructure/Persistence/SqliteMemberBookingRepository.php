<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\Port\MemberBookingRepositoryInterface;
use PDO;

/** @phpstan-import-type AvailableGuest from \App\Application\Port\MemberBookingRepositoryInterface */
final readonly class SqliteMemberBookingRepository implements MemberBookingRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    /** @param list<mixed> $params */
    private function execute(string $sql,array $params): \PDOStatement { foreach($params as $value) { if($value!==null&&!is_scalar($value))throw new \UnexpectedValueException("Invalid database parameter"); } $s=$this->pdo->prepare($sql); $s->execute($params); return $s; }
    /** @return list<array{id:int,tripId:int}> */
    public function ownedBookingIndex(string $owner, int $after): array
    {
        $statement=$this->execute('SELECT id,trip_id FROM registrations WHERE owner_member_reference=? AND id>? ORDER BY id LIMIT 26',[$owner,$after]);
        $result=[];
        foreach($statement->fetchAll(\PDO::FETCH_ASSOC) as $row){
            $stored=StoredRow::from($row);
            $result[]=['id'=>$stored->integer('id'),'tripId'=>$stored->integer('trip_id')];
        }
        return $result;
    }
    public function activePersonJob(mixed $person): int|string|false|null
    {
        $s=$this->execute("SELECT 1 FROM participation_jobs WHERE participant_id=? AND status IN ('pending','running','unknown','reconciling')", [$person]);
        return $s->fetchColumn();
    }
    public function duplicateMember(mixed $trip, mixed $member, mixed $booking): int|string|false|null
    {
        $s=$this->execute("SELECT 1 FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE r.trip_id=? AND p.member_reference=? AND p.guest_approval_id IS NULL AND p.participation_status<>'cancelled' AND r.id<>?", [$trip, $member, $booking]);
        return $s->fetchColumn();
    }
    public function setPersonGuest(mixed $guest, mixed $person): void
    {
        $s=$this->execute('UPDATE registration_participants SET guest_approval_id=? WHERE id=?', [$guest, $person]);
    }
    public function claimGuest(mixed $person, mixed $guest, mixed $owner, mixed $trip): int
    {
        $s=$this->execute("UPDATE guest_approvals SET participant_id=? WHERE id=? AND owner_member=? AND trip_id=? AND participant_id IS NULL AND status='approved'", [$person, $guest, $owner, $trip]); return $s->rowCount();
    }
    public function setBookingOwner(mixed $owner, mixed $booking): void
    {
        $s=$this->execute('UPDATE registrations SET owner_member_reference=? WHERE id=?', [$owner, $booking]);
    }
    public function createGuest(mixed $trip, mixed $owner, mixed $parent, mixed $name, mixed $birthDate, mixed $priceGroup, mixed $price, mixed $actor, mixed $createdAt, mixed $reason, mixed $operationKey, mixed $requestHash): int
    {
        $s=$this->execute('INSERT INTO guest_approvals(trip_id,owner_member,parent_member,name,birth_date,price_group,price,actor_id,created_at,reason,operation_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$trip, $owner, $parent, $name, $birthDate, $priceGroup, $price, $actor, $createdAt, $reason, $operationKey, $requestHash]);
        return StoredRow::from(["id"=>$this->pdo->lastInsertId()])->integer("id");
    }
    /** @return AvailableGuest|false */
    public function availableGuest(mixed $guest, mixed $trip, mixed $owner): array|false
    {
        $s=$this->execute("SELECT * FROM guest_approvals WHERE id=? AND trip_id=? AND owner_member=? AND status='approved' AND participant_id IS NULL", [$guest, $trip, $owner]);
        $row=StoredRow::fetch($s); if($row===false)return false;  $r=StoredRow::from($row);return ['id'=>$r->integer('id'),'name'=>$r->text('name'),'birth_date'=>$r->text('birth_date'),'parent_member'=>$r->text('parent_member'),'price_group'=>$r->text('price_group'),'price'=>$r->text('price')]+$row;
    }
    /** @return list<int> */
    public function ownedBookingIds(mixed $trip, mixed $owner, mixed $after): array
    {
        $s=$this->execute('SELECT id FROM registrations WHERE trip_id=? AND owner_member_reference=? AND id>? ORDER BY id LIMIT 26', [$trip, $owner, $after]); return array_values(array_map(static fn(mixed $id): int => StoredRow::from(['id'=>$id])->integer('id'),$s->fetchAll(PDO::FETCH_COLUMN)));
    }
    public function bookingOwner(mixed $trip, mixed $booking): int|string|false|null
    {
        $s=$this->execute('SELECT owner_member_reference FROM registrations WHERE trip_id=? AND id=?', [$trip, $booking]);
        return $s->fetchColumn();
    }
    /** @return list<array<string,scalar|null>> */
    public function availableGuests(mixed $trip, mixed $owner): array
    {
        $s=$this->execute("SELECT id,name,birth_date AS birthDate,parent_member AS parentMemberReference,price_group AS priceGroupId,price AS expectedPrice FROM guest_approvals WHERE trip_id=? AND owner_member=? AND status='approved' AND participant_id IS NULL", [$trip, $owner]);
        $rows=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $rows[]=StoredRow::scalars($row); } return $rows;
    }
    public function tripForEvent(mixed $event): int|string|false|null
    {
        $s=$this->execute('SELECT trip_id FROM external_event_links WHERE external_id=?', [$event]);
        return $s->fetchColumn();
    }
    /** @return array<string,scalar|null>|false */
    public function settings(mixed $trip): array|false
    {
        $s=$this->execute('SELECT * FROM member_trip_settings WHERE trip_id=?', [$trip]);
        $row=StoredRow::fetch($s); if($row===false)return false; return $row;
    }
    public function auditMember(mixed $member, mixed $service, mixed $action, mixed $trip, mixed $createdAt): void
    {
        $s=$this->execute('INSERT INTO member_audit (member_reference,service_id,action,trip_id,created_at) VALUES (?,?,?,?,?)', [$member, $service, $action, $trip, $createdAt]);
    }
    /** @return array{externalId:string,confirmedAt:string,data:array<array-key,mixed>}|null */
    public function link(int $trip): ?array { return (new SqliteTermRepository($this->pdo))->link($trip); }
}
