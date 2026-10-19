<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\Port\BookingRepositoryInterface;
use App\Application\AccessException;
use PDO;

/**
 * @phpstan-import-type BookingPerson from BookingRepositoryInterface
 * @phpstan-import-type AnswerRevision from BookingRepositoryInterface
 * @phpstan-import-type Booking from BookingRepositoryInterface
 * @phpstan-import-type BookingOperation from BookingRepositoryInterface
 */
final readonly class SqliteBookingRepository implements BookingRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    /** @param array<array-key,mixed> $p */
    private function query(string $sql,array $p=[]): \PDOStatement { $s=$this->pdo->prepare($sql);$s->execute($p);return $s; }
    /**
     * @template T
     * @param callable():T $read
     * @return T
     */
    public function snapshot(callable $read): mixed
    {
        if($this->pdo->inTransaction()){return $read();}
        $this->pdo->beginTransaction();
        try{$result=$read();$this->pdo->commit();return $result;}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }
    /** @return list<Booking> */
    public function list(int $tripId): array
    {
        $bookings=[];
        foreach($this->query('SELECT id FROM registrations WHERE trip_id=? ORDER BY id DESC',[$tripId])->fetchAll(PDO::FETCH_COLUMN) as $value){
            $id=StoredRow::from(['id'=>$value])->integer('id');
            $booking=$this->get($tripId,$id);
            if($booking!==null)$bookings[]=$booking;
        }
        return $bookings;
    }
    /** @return Booking|null */
    public function get(int $tripId,int $id): ?array
    {
        $value=$this->query('SELECT * FROM registrations WHERE trip_id=? AND id=?',[$tripId,$id])->fetch(PDO::FETCH_ASSOC);
        if($value===false)return null;
        $r=StoredRow::from($value);
        $persons=[];
        foreach($this->query('SELECT * FROM registration_participants WHERE registration_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC) as $value){
            $persons[]=$this->person(StoredRow::from($value));
        }
        $revisions=[];$current=[];
        foreach($this->query('SELECT * FROM answer_revisions WHERE registration_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC) as $value){
            $a=$this->answer(StoredRow::from($value));$revisions[]=$a;
            $current[($a['participantId']??'booking').':'.$a['fieldId']]=$a;
        }
        $personRevisions=[];
        foreach($this->query('SELECT pr.* FROM participant_revisions pr JOIN registration_participants p ON p.id=pr.participant_id WHERE p.registration_id=? ORDER BY pr.id',[$id])->fetchAll(PDO::FETCH_ASSOC) as $value){
            $personRevisions[]=StoredRow::from($value)->values();
        }
        $pending=StoredRow::from(['count'=>$this->query("SELECT COUNT(*) FROM form_supplement_requests WHERE registration_id=? AND status='pending'",[$id])->fetchColumn()])->integer('count');
        return ['id'=>$id,'tripId'=>$tripId,'reference'=>$r->nullableText('reference')??'ALT-'.$id,
            'source'=>$r->nullableText('owner_member_reference')!==null?'website':$r->text('source'),'contactIdentity'=>$r->nullableText('contact_identity'),
            'versionId'=>$r->nullableInteger('form_version_id'),'status'=>$r->text('booking_status'),
            'syncStatus'=>$r->text('sync_status'),'roomCategory'=>$r->text('room_category')===''?null:$r->text('room_category'),
            'rooms'=>\App\Application\BookingRooms::validateStored($r->jsonArray('rooms_json')),'roomAssignment'=>$r->nullableText('room_assignment'),'comment'=>$r->text('comment'),
            'receivedAt'=>$r->text('received_at'),'revision'=>$r->integer('revision'),'managed'=>$r->integer('managed_booking')!==0,
            'persons'=>$persons,'personRevisions'=>$personRevisions,'answers'=>array_values($current),
            'revisions'=>$revisions,'supplementPending'=>$pending>0];
    }
    /** @return BookingPerson */
    private function person(StoredRow $p): array
    {
        return ['id'=>$p->integer('id'),'clientReference'=>$p->nullableText('client_reference'),
            'name'=>$p->text('name'),'birthDate'=>$p->text('birth_date'),'status'=>$p->text('participation_status'),
            'syncStatus'=>$p->text('sync_status'),'memberReference'=>$p->nullableText('member_reference'),
            'externalReference'=>$p->nullableText('external_reference'),'priceGroupId'=>$p->nullableText('price_group_reference'),
            'expectedPrice'=>$p->nullableText('expected_price'),'guestId'=>$p->nullableInteger('guest_approval_id'),
            'externalPriceGroupId'=>$p->nullableText('external_price_group'),'externalPrice'=>$p->nullableText('external_price')];
    }
    /** @return AnswerRevision */
    private function answer(StoredRow $a): array
    {
        $json=$a->nullableText('value_json');
        return ['id'=>$a->integer('id'),'participantId'=>$a->nullableInteger('participant_id'),
            'versionId'=>$a->integer('version_id'),'fieldId'=>$a->text('field_id'),'state'=>$a->text('state'),
            'value'=>$json===null?null:json_decode($json,true,512,JSON_THROW_ON_ERROR),
            'actorId'=>$a->integer('actor_id'),'createdAt'=>$a->text('created_at'),'reason'=>$a->text('reason'),
            'bookingRevision'=>$a->integer('booking_revision')];
    }
    /** @return BookingOperation|null */
    public function operation(int $tripId,string $key): ?array
    {
        $value=$this->query('SELECT request_hash,registration_id FROM booking_operations WHERE trip_id=? AND operation_key=?',[$tripId,$key])->fetch(PDO::FETCH_ASSOC);
        if($value===false)return null;
        $row=StoredRow::from($value);
        return ['request_hash'=>$row->text('request_hash'),'registration_id'=>$row->integer('registration_id')];
    }
    public function recordOperation(int $tripId,string $key,string $hash,int $id): void { $this->query('INSERT INTO booking_operations (trip_id,operation_key,request_hash,registration_id) VALUES (?,?,?,?)',[$tripId,$key,$hash,$id]); }
    /** @param array<array-key,mixed> $d */
    public function create(int $tripId,array $d): int
    {
        $this->query("INSERT INTO registrations (trip_id,room_category,received_at,comment,source,reference,contact_identity,form_version_id,booking_status,sync_status,managed_booking,revision) VALUES (?,?,?,?,'manual',?,?,?,'pending','pending',1,1)",[$tripId,$d['roomCategory']??'',gmdate(DATE_ATOM),$d['comment']??'','B-'.bin2hex(random_bytes(12)),$d['contactIdentity'],$d['versionId']]);
        $id=(int)$this->pdo->lastInsertId();
        foreach(\App\Application\Data\BookingPersonData::list($d['persons']) as $p){$this->query("INSERT INTO registration_participants (registration_id,name,birth_date,client_reference,price_group_reference,expected_price,participation_status,sync_status) VALUES (?,?,?,?,?,?,'pending','pending')",[$id,$p['name'],$p['birthDate'],$p['id'],$p['priceGroupId']??null,$p['expectedPrice']??null]);}
        $map=[];foreach($this->query('SELECT id,client_reference FROM registration_participants WHERE registration_id=?',[$id])->fetchAll(PDO::FETCH_ASSOC) as $row){$stored=StoredRow::from($row);$map[$stored->text('client_reference')]=$stored->integer('id');}
        $rooms=[];foreach(\App\Application\BookingRooms::validateStored($d['rooms']??[]) as $room){$rooms[]=['category'=>$room['category'],'personIds'=>array_map(static fn($pid)=>$map[(string)$pid],$room['personIds'])];}
        $this->query('UPDATE registrations SET rooms_json=? WHERE id=?',[json_encode($rooms,JSON_THROW_ON_ERROR),$id]);
        return $id;
    }
    /** @param array<array-key,mixed> $values */
    public function revise(int $id,int $revision,array $values): void
    {
        $allowed=['booking_status','form_version_id','room_assignment','rooms_json','room_category'];$sets=['revision=revision+1'];$p=[];
        foreach($values as $k=>$v){if(!in_array($k,$allowed,true)){throw new \LogicException('Invalid booking metadata');}$sets[]="$k=?";$p[]=$v;}
        $p[]=$id;$p[]=$revision;
        if($this->query('UPDATE registrations SET '.implode(',',$sets).' WHERE id=? AND revision=?',$p)->rowCount()!==1){throw new AccessException('revision_conflict',409);}
    }
    /** @param array<array-key,mixed> $person */
    public function correctPerson(int $bookingId,array $person,int $actor,string $reason): void
    {
        $old=$this->query('SELECT name,birth_date FROM registration_participants WHERE registration_id=? AND id=?',[$bookingId,$person['id']])->fetch(PDO::FETCH_ASSOC);
        $next=['name'=>$person['name'],'birth_date'=>$person['birthDate']];
        if($old===$next){return;}
        $this->query('INSERT INTO participant_revisions (participant_id,before_json,after_json,actor_id,created_at,reason) VALUES (?,?,?,?,?,?)',[$person['id'],json_encode($old,JSON_THROW_ON_ERROR),json_encode($next,JSON_THROW_ON_ERROR),$actor,gmdate(DATE_ATOM),$reason]);
        $this->query('UPDATE registration_participants SET name=?,birth_date=? WHERE registration_id=? AND id=?',[$person['name'],$person['birthDate'],$bookingId,$person['id']]);
    }
    public function cancelPerson(int $id,int $personId): void { $this->query("UPDATE registration_participants SET participation_status='cancelled' WHERE registration_id=? AND id=?",[$id,$personId]); }
    /** @param array<array-key,mixed> $entry */
    public function append(int $id,?int $personId,int $versionId,string $fieldId,array $entry,int $actor,int $revision,string $reason): void
    {
        $this->query('INSERT INTO answer_revisions (registration_id,participant_id,version_id,field_id,state,value_json,actor_id,created_at,reason,booking_revision) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$personId,$versionId,$fieldId,$entry['state'],$entry['value']===null?null:json_encode($entry['value'],JSON_THROW_ON_ERROR),$actor,gmdate(DATE_ATOM),$reason,$revision]);
    }
    public function supplements(int $id): void { $this->query("UPDATE form_supplement_requests SET status='completed' WHERE registration_id=? AND status='pending'",[$id]); }
}
