<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\AccessException;
use App\Application\Port\FormRepositoryInterface;
use PDO;

final readonly class SqliteFormRepository implements FormRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    private function query(string $sql, array $parameters = []): \PDOStatement { $s = $this->pdo->prepare($sql); $s->execute($parameters); return $s; }
    private function header(array $r): array
    {
        return ['id' => (int) $r['id'], 'eventId' => (int) $r['event_id'], 'number' => $r['number'] === null ? null : (int) $r['number'], 'status' => $r['status'], 'revision' => (int) $r['revision'], 'basedOnId' => $r['based_on_id'] === null ? null : (int) $r['based_on_id'], 'createdBy' => (int) $r['created_by'], 'createdAt' => $r['created_at'], 'publishedBy' => $r['published_by'] === null ? null : (int) $r['published_by'], 'publishedAt' => $r['published_at'], 'changeDescription' => $r['change_description']];
    }
    public function list(int $eventId): array
    {
        return array_map(fn ($r) => $this->get((int) $r['id']), $this->query('SELECT id FROM form_versions WHERE event_id=? ORDER BY id DESC', [$eventId])->fetchAll(PDO::FETCH_ASSOC));
    }
    public function get(int $id): ?array
    {
        $r = $this->query('SELECT * FROM form_versions WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return null; }
        $fields = $this->query('SELECT definition FROM form_fields WHERE version_id=? ORDER BY position', [$id])->fetchAll(PDO::FETCH_COLUMN);
        return $this->header($r) + ['fields' => array_map(static fn ($v) => json_decode($v, true, 512, JSON_THROW_ON_ERROR), $fields)];
    }
    public function create(int $eventId, ?int $basedOnId, int $actor, array $fields): array
    {
        $this->query('INSERT INTO form_versions (event_id,based_on_id,created_by,created_at) VALUES (?,?,?,?)', [$eventId,$basedOnId,$actor,gmdate(DATE_ATOM)]);
        $id = (int) $this->pdo->lastInsertId(); $this->fields($id, $fields); return $this->get($id);
    }
    private function fields(int $id, array $fields): void
    {
        $this->query('DELETE FROM form_fields WHERE version_id=?', [$id]);
        foreach ($fields as $i => $f) { $this->query('INSERT INTO form_fields (version_id,field_id,position,definition) VALUES (?,?,?,?)', [$id,$f['id'],$i,json_encode($f, JSON_THROW_ON_ERROR)]); }
    }
    public function save(int $id, int $revision, array $fields): array
    {
        $s = $this->query("UPDATE form_versions SET revision=revision+1 WHERE id=? AND revision=? AND status='draft'", [$id,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        $this->fields($id,$fields); return $this->get($id);
    }
    public function publish(int $id, int $revision, int $actor, string $description): array
    {
        $v = $this->get($id);
        $number = (int) $this->query('SELECT COALESCE(MAX(number),0)+1 FROM form_versions WHERE event_id=?', [$v['eventId']])->fetchColumn();
        $s = $this->query("UPDATE form_versions SET status='published',number=?,published_by=?,published_at=?,change_description=?,revision=revision+1 WHERE id=? AND revision=? AND status='draft'", [$number,$actor,gmdate(DATE_ATOM),$description,$id,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        return $this->get($id);
    }
    public function archive(int $id, int $revision): array
    {
        $s = $this->query("UPDATE form_versions SET status='archived',revision=revision+1 WHERE id=? AND revision=? AND status='published'", [$id,$revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        return $this->get($id);
    }
    public function binding(int $tripId): array
    {
        $r = $this->query('SELECT form_version_id,form_revision FROM trips WHERE id=?', [$tripId])->fetch(PDO::FETCH_ASSOC);
        $history = $this->query('SELECT * FROM trip_form_history WHERE trip_id=? ORDER BY revision DESC', [$tripId])->fetchAll(PDO::FETCH_ASSOC);
        return ['versionId' => $r['form_version_id'] === null ? null : (int) $r['form_version_id'], 'revision' => (int) $r['form_revision'], 'history' => array_map(static fn ($h) => ['id' => (int) $h['id'],'fromVersionId' => $h['from_version_id'] === null ? null : (int) $h['from_version_id'],'toVersionId' => (int) $h['to_version_id'],'revision' => (int) $h['revision'],'actorId' => (int) $h['actor_id'],'createdAt' => $h['created_at'],'reason' => $h['reason'],'requestSupplement' => (bool) $h['request_supplement']], $history)];
    }
    public function registrationCount(int $tripId): int { return (int) $this->query('SELECT COUNT(*) FROM registrations WHERE trip_id=?', [$tripId])->fetchColumn(); }
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
