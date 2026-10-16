<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use PDO;

/** Durable planning snapshots, local term drafts and one creation operation per trip. */
/** @phpstan-import-type CreationJob from \App\Application\Port\TermRepositoryInterface */
final readonly class SqliteWorkflowRepository
{
    public function __construct(private PDO $pdo) {}
    public function query(string $sql,array $params=[]): \PDOStatement
    {
        $s=$this->pdo->prepare($sql);$s->execute($params);return $s;
    }
    public function transferRevision(int $tripId): int {return (int)$this->query('SELECT planning_transfer_revision FROM trips WHERE id=?',[$tripId])->fetchColumn();}
    public function advanceTransferRevision(int $tripId): void {$this->query('UPDATE trips SET planning_transfer_revision=planning_transfer_revision+1 WHERE id=?',[$tripId]);}
    public function snapshot(int $tripId,?string $key=null): ?array
    {
        $row=$this->query('SELECT * FROM calculation_snapshots WHERE trip_id=?'.($key===null?' ORDER BY id DESC LIMIT 1':' AND operation_key=?'),$key===null?[$tripId]:[$tripId,$key])->fetch(PDO::FETCH_ASSOC);
        if(!$row){return null;}
        return ['id'=>(int)$row['id'],'sourceHash'=>$row['source_hash'],'actorId'=>(int)$row['actor_id'],'createdAt'=>$row['created_at'],'requestHash'=>$row['request_hash'],'data'=>json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR)];
    }
    public function saveSnapshot(int $tripId,string $sourceHash,array $data,int $actor,string $key,string $requestHash): array
    {
        $this->query('INSERT INTO calculation_snapshots (trip_id,source_hash,snapshot_json,actor_id,created_at,operation_key,request_hash) VALUES (?,?,?,?,?,?,?)',[$tripId,$sourceHash,json_encode($data,JSON_THROW_ON_ERROR),$actor,gmdate(DATE_ATOM),$key,$requestHash]);
        return $this->snapshot($tripId,$key);
    }
    /** @return array{revision: int, data: array<array-key, mixed>}|null */
    public function draft(int $tripId): ?array
    {
        return (new SqliteTermRepository($this->pdo))->draft($tripId);
    }
    /** @phpstan-impure
     * @return array{externalId: string, confirmedAt: string, data: array<array-key, mixed>}|null
     */
    public function link(int $tripId): ?array
    {
        return (new SqliteTermRepository($this->pdo))->link($tripId);
    }
    /** @return CreationJob|null */
    public function job(int $tripId): ?array
    {
        return (new SqliteTermRepository($this->pdo))->job($tripId);
    }
}
