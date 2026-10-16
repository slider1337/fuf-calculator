<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\AccessException;
use App\Application\Port\FormRepositoryInterface;
use PDO;

/**
 * @phpstan-import-type FormVersion from FormRepositoryInterface
 * @phpstan-import-type FormHeader from FormRepositoryInterface
 * @phpstan-import-type BindingHistory from FormRepositoryInterface
 * @phpstan-import-type FormBinding from FormRepositoryInterface
 */
final readonly class SqliteFormRepository implements FormRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    private function query(string $sql, array $parameters = []): \PDOStatement { $s = $this->pdo->prepare($sql); $s->execute($parameters); return $s; }
    /** @param array<array-key,mixed> $value
     * @return FormHeader */
    private function header(array $value): array
    {
        $r=StoredRow::from($value);
        return ['id'=>$r->integer('id'),'eventId'=>$r->integer('event_id'),'number'=>$r->nullableInteger('number'),
            'status'=>$r->text('status'),'revision'=>$r->integer('revision'),'basedOnId'=>$r->nullableInteger('based_on_id'),
            'createdBy'=>$r->integer('created_by'),'createdAt'=>$r->text('created_at'),'publishedBy'=>$r->nullableInteger('published_by'),
            'publishedAt'=>$r->nullableText('published_at'),'changeDescription'=>$r->text('change_description')];
    }
    /** @return list<FormVersion> */
    public function list(int $eventId): array
    {
        $result=[];
        foreach($this->query('SELECT id FROM form_versions WHERE event_id=? ORDER BY id DESC',[$eventId])->fetchAll(PDO::FETCH_ASSOC) as $value){
            $id=StoredRow::from($value)->integer('id');$version=$this->get($id);
            if($version!==null)$result[]=$version;
        }
        return $result;
    }
    /** @phpstan-impure
     * @return FormVersion|null */
    public function get(int $id): ?array
    {
        $r = $this->query('SELECT * FROM form_versions WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return null; }
        $fields = $this->query('SELECT definition FROM form_fields WHERE version_id=? ORDER BY position', [$id])->fetchAll(PDO::FETCH_COLUMN);
        $definitions=[];
        foreach($fields as $value){
            $field=StoredRow::from(['json'=>$value])->jsonArray('json');
            foreach(['id','scope','label','type','unit'] as $key){if(!is_string($field[$key]??null))throw new \UnexpectedValueException('Invalid stored form field text');}
            if(!array_key_exists('mapping',$field)||($field['mapping']!==null&&!is_string($field['mapping'])))throw new \UnexpectedValueException('Invalid stored form mapping');
            $field['id']=StoredRow::from($field)->text('id');
            $field['scope']=StoredRow::from($field)->text('scope');
            $field['label']=StoredRow::from($field)->text('label');
            $field['type']=StoredRow::from($field)->text('type');
            $field['unit']=StoredRow::from($field)->text('unit');
            $definitions[]=$field;
        }
        return $this->header($r)+['fields'=>$definitions];
    }
    /** @return FormVersion */
    public function create(int $eventId, ?int $basedOnId, int $actor, array $fields): array
    {
        $this->query('INSERT INTO form_versions (event_id,based_on_id,created_by,created_at) VALUES (?,?,?,?)', [$eventId,$basedOnId,$actor,gmdate(DATE_ATOM)]);
        $id = (int) $this->pdo->lastInsertId(); $this->fields($id, $fields); return $this->get($id)??throw new \App\Application\NotFoundException('Form version');
    }
    private function fields(int $id, array $fields): void
    {
        $this->query('DELETE FROM form_fields WHERE version_id=?', [$id]);
        foreach ($fields as $i => $f) { $this->query('INSERT INTO form_fields (version_id,field_id,position,definition) VALUES (?,?,?,?)', [$id,$f['id'],$i,json_encode($f, JSON_THROW_ON_ERROR)]); }
    }
    /** @return FormVersion */
    public function save(int $id, int $revision, array $fields): array
    {
        $s = $this->query("UPDATE form_versions SET revision=revision+1 WHERE id=? AND revision=? AND status='draft'", [$id,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        $this->fields($id,$fields); return $this->get($id)??throw new \App\Application\NotFoundException('Form version');
    }
    /** @return FormVersion */
    public function publish(int $id, int $revision, int $actor, string $description): array
    {
        $v = $this->get($id)??throw new \App\Application\NotFoundException('Form version');
        $number = (int) $this->query('SELECT COALESCE(MAX(number),0)+1 FROM form_versions WHERE event_id=?', [$v['eventId']])->fetchColumn();
        $s = $this->query("UPDATE form_versions SET status='published',number=?,published_by=?,published_at=?,change_description=?,revision=revision+1 WHERE id=? AND revision=? AND status='draft'", [$number,$actor,gmdate(DATE_ATOM),$description,$id,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        return $this->get($id)??throw new \App\Application\NotFoundException('Form version');
    }
    /** @return FormVersion */
    public function archive(int $id, int $revision): array
    {
        $s = $this->query("UPDATE form_versions SET status='archived',revision=revision+1 WHERE id=? AND revision=? AND status='published'", [$id,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        return $this->get($id)??throw new \App\Application\NotFoundException('Form version');
    }
    /** @return FormBinding */
    public function binding(int $tripId): array
    {
        $r = $this->query('SELECT form_version_id,form_revision FROM trips WHERE id=?', [$tripId])->fetch(PDO::FETCH_ASSOC);
        $history = $this->query('SELECT * FROM trip_form_history WHERE trip_id=? ORDER BY revision DESC', [$tripId])->fetchAll(PDO::FETCH_ASSOC);
        if($r===false)throw new \App\Application\NotFoundException('Trip');
        $r=StoredRow::from($r);$rows=[];
        foreach($history as $value){$h=StoredRow::from($value);$rows[]=['id'=>$h->integer('id'),
            'fromVersionId'=>$h->nullableInteger('from_version_id'),'toVersionId'=>$h->integer('to_version_id'),
            'revision'=>$h->integer('revision'),'actorId'=>$h->integer('actor_id'),'createdAt'=>$h->text('created_at'),
            'reason'=>$h->text('reason'),'requestSupplement'=>$h->integer('request_supplement')!==0];}
        return ['versionId'=>$r->nullableInteger('form_version_id'),'revision'=>$r->integer('form_revision'),'history'=>$rows];
    }
    /** @return list<array{versionId:?int,count:int}> */
    public function registrationVersions(int $tripId): array
    {
        $rows=[];
        foreach($this->query('SELECT form_version_id,COUNT(*) AS n FROM registrations WHERE trip_id=? GROUP BY form_version_id',[$tripId])->fetchAll(PDO::FETCH_ASSOC) as $value){
            $r=StoredRow::from($value);$rows[]=['versionId'=>$r->nullableInteger('form_version_id'),'count'=>$r->integer('n')];
        }
        return $rows;
    }
    public function registrationCount(int $tripId): int { return (int) $this->query('SELECT COUNT(*) FROM registrations WHERE trip_id=?', [$tripId])->fetchColumn(); }
    /** @return FormBinding */
    public function switchVersion(int $tripId, int $revision, int $versionId, int $actor, string $reason, bool $supplement): array
    {
        $old = $this->binding($tripId);
        $s = $this->query('UPDATE trips SET form_version_id=?,form_revision=form_revision+1 WHERE id=? AND form_revision=?', [$versionId,$tripId,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        $this->query('INSERT INTO trip_form_history (trip_id,from_version_id,to_version_id,revision,actor_id,created_at,reason,request_supplement) VALUES (?,?,?,?,?,?,?,?)', [$tripId,$old['versionId'],$versionId,$revision+1,$actor,gmdate(DATE_ATOM),$reason,(int) $supplement]);
        $hid = (int) $this->pdo->lastInsertId();
        if ($supplement) { $this->query("INSERT INTO form_supplement_requests (history_id,registration_id,registration_reference,status) SELECT ?,id,id,'pending' FROM registrations WHERE trip_id=?", [$hid,$tripId]); }
        return $this->binding($tripId);
    }
}
