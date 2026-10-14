<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\Port\BookingRepositoryInterface;
use App\Application\AccessException;
use PDO;

final readonly class SqliteBookingRepository implements BookingRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    private function query(string $sql,array $p=[]): \PDOStatement { $s=$this->pdo->prepare($sql);$s->execute($p);return $s; }
    public function snapshot(callable $read): mixed
    {
        if($this->pdo->inTransaction()){return $read();}
        $this->pdo->beginTransaction();
        try{$result=$read();$this->pdo->commit();return $result;}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }
    public function list(int $tripId): array
    {
        return array_map(fn($id)=>$this->get($tripId,(int)$id),$this->query('SELECT id FROM registrations WHERE trip_id=? ORDER BY id DESC',[$tripId])->fetchAll(PDO::FETCH_COLUMN));
    }
    public function get(int $tripId,int $id): ?array
    {
        $r=$this->query('SELECT * FROM registrations WHERE trip_id=? AND id=?',[$tripId,$id])->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return null; }
        $persons=array_map(static fn($p)=>['id'=>(int)$p['id'],'clientReference'=>$p['client_reference'],'name'=>$p['name'],'birthDate'=>$p['birth_date'],'status'=>$p['participation_status'],'syncStatus'=>$p['sync_status'],'memberReference'=>$p['member_reference']],$this->query('SELECT * FROM registration_participants WHERE registration_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC));
        $revisions=array_map(static fn($a)=>['id'=>(int)$a['id'],'participantId'=>$a['participant_id']===null?null:(int)$a['participant_id'],'versionId'=>(int)$a['version_id'],'fieldId'=>$a['field_id'],'state'=>$a['state'],'value'=>$a['value_json']===null?null:json_decode($a['value_json'],true,512,JSON_THROW_ON_ERROR),'actorId'=>(int)$a['actor_id'],'createdAt'=>$a['created_at'],'reason'=>$a['reason'],'bookingRevision'=>(int)$a['booking_revision']],$this->query('SELECT * FROM answer_revisions WHERE registration_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC));
        $current=[];foreach($revisions as $a){$current[($a['participantId']??'booking').':'.$a['fieldId']]=$a;}
        return ['id'=>$id,'tripId'=>$tripId,'reference'=>$r['reference']??'ALT-'.$id,'source'=>$r['source'],'contactIdentity'=>$r['contact_identity'],'versionId'=>$r['form_version_id']===null?null:(int)$r['form_version_id'],'status'=>$r['booking_status'],'syncStatus'=>$r['sync_status'],'roomCategory'=>$r['room_category']===''?null:$r['room_category'],'roomAssignment'=>$r['room_assignment'],'comment'=>$r['comment'],'receivedAt'=>$r['received_at'],'revision'=>(int)$r['revision'],'managed'=>(bool)$r['managed_booking'],'persons'=>$persons,'personRevisions'=>$this->query('SELECT pr.* FROM participant_revisions pr JOIN registration_participants p ON p.id=pr.participant_id WHERE p.registration_id=? ORDER BY pr.id',[$id])->fetchAll(PDO::FETCH_ASSOC),'answers'=>array_values($current),'revisions'=>$revisions,'supplementPending'=>(int)$this->query("SELECT COUNT(*) FROM form_supplement_requests WHERE registration_id=? AND status='pending'",[$id])->fetchColumn()>0];
    }
    public function operation(int $tripId,string $key): ?array { return $this->query('SELECT request_hash,registration_id FROM booking_operations WHERE trip_id=? AND operation_key=?',[$tripId,$key])->fetch(PDO::FETCH_ASSOC)?:null; }
    public function recordOperation(int $tripId,string $key,string $hash,int $id): void { $this->query('INSERT INTO booking_operations (trip_id,operation_key,request_hash,registration_id) VALUES (?,?,?,?)',[$tripId,$key,$hash,$id]); }
    public function create(int $tripId,array $d): int
    {
        $this->query("INSERT INTO registrations (trip_id,room_category,received_at,comment,source,reference,contact_identity,form_version_id,booking_status,sync_status,managed_booking,revision) VALUES (?,?,?,?,'manual',?,?,?,'pending','pending',1,1)",[$tripId,$d['roomCategory']??'',gmdate(DATE_ATOM),$d['comment']??'','B-'.bin2hex(random_bytes(12)),$d['contactIdentity'],$d['versionId']]);
        $id=(int)$this->pdo->lastInsertId();
        foreach($d['persons'] as $p){$this->query("INSERT INTO registration_participants (registration_id,name,birth_date,client_reference,participation_status,sync_status) VALUES (?,?,?,?,'pending','pending')",[$id,$p['name'],$p['birthDate'],$p['id']]);}
        return $id;
    }
    public function revise(int $id,int $revision,array $values): void
    {
        $allowed=['booking_status','form_version_id','room_assignment'];$sets=['revision=revision+1'];$p=[];
        foreach($values as $k=>$v){if(!in_array($k,$allowed,true)){throw new \LogicException('Invalid booking metadata');}$sets[]="$k=?";$p[]=$v;}
        $p[]=$id;$p[]=$revision;
        if($this->query('UPDATE registrations SET '.implode(',',$sets).' WHERE id=? AND revision=?',$p)->rowCount()!==1){throw new AccessException('revision_conflict',409);}
    }
    public function correctPerson(int $bookingId,array $person,int $actor,string $reason): void
    {
        $old=$this->query('SELECT name,birth_date FROM registration_participants WHERE registration_id=? AND id=?',[$bookingId,$person['id']])->fetch(PDO::FETCH_ASSOC);
        $next=['name'=>$person['name'],'birth_date'=>$person['birthDate']];
        if($old===$next){return;}
        $this->query('INSERT INTO participant_revisions (participant_id,before_json,after_json,actor_id,created_at,reason) VALUES (?,?,?,?,?,?)',[$person['id'],json_encode($old,JSON_THROW_ON_ERROR),json_encode($next,JSON_THROW_ON_ERROR),$actor,gmdate(DATE_ATOM),$reason]);
        $this->query('UPDATE registration_participants SET name=?,birth_date=? WHERE registration_id=? AND id=?',[$person['name'],$person['birthDate'],$bookingId,$person['id']]);
    }
    public function cancelPerson(int $id,int $personId): void { $this->query("UPDATE registration_participants SET participation_status='cancelled' WHERE registration_id=? AND id=?",[$id,$personId]); }
    public function append(int $id,?int $personId,int $versionId,string $fieldId,array $entry,int $actor,int $revision,string $reason): void
    {
        $this->query('INSERT INTO answer_revisions (registration_id,participant_id,version_id,field_id,state,value_json,actor_id,created_at,reason,booking_revision) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$personId,$versionId,$fieldId,$entry['state'],$entry['value']===null?null:json_encode($entry['value'],JSON_THROW_ON_ERROR),$actor,gmdate(DATE_ATOM),$reason,$revision]);
    }
    public function supplements(int $id): void { $this->query("UPDATE form_supplement_requests SET status='completed' WHERE registration_id=? AND status='pending'",[$id]); }
}
