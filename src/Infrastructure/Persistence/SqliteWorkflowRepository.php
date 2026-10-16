<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use PDO;

/**
 * Durable planning snapshots, local term drafts and one creation operation per trip.
 * @phpstan-import-type CreationJob from \App\Application\Port\TermRepositoryInterface
 * @phpstan-type PlanningSnapshot array{id:int,sourceHash:string,actorId:int,createdAt:string,requestHash:string,data:array<array-key,mixed>}
 */
final readonly class SqliteWorkflowRepository
{
    public function __construct(private PDO $pdo) {}
    /** @param array<array-key,mixed> $params */
    public function query(string $sql,array $params=[]): \PDOStatement
    {
        $s=$this->pdo->prepare($sql);$s->execute($params);return $s;
    }
    public function transferRevision(int $tripId): int {return (int)$this->query('SELECT planning_transfer_revision FROM trips WHERE id=?',[$tripId])->fetchColumn();}
    public function advanceTransferRevision(int $tripId): void {$this->query('UPDATE trips SET planning_transfer_revision=planning_transfer_revision+1 WHERE id=?',[$tripId]);}
    /** @return PlanningSnapshot|null */
    public function snapshot(int $tripId,?string $key=null): ?array
    {
        $row=$this->query('SELECT * FROM calculation_snapshots WHERE trip_id=?'.($key===null?' ORDER BY id DESC LIMIT 1':' AND operation_key=?'),$key===null?[$tripId]:[$tripId,$key])->fetch(PDO::FETCH_ASSOC);
        if(!$row){return null;}
        $r=StoredRow::from($row);
        return ['id'=>$r->integer('id'),'sourceHash'=>$r->text('source_hash'),'actorId'=>$r->integer('actor_id'),'createdAt'=>$r->text('created_at'),'requestHash'=>$r->text('request_hash'),'data'=>$r->jsonArray('snapshot_json')];
    }
    /**
     * @param array<array-key,mixed> $data
     * @return PlanningSnapshot
     */
    public function saveSnapshot(int $tripId,string $sourceHash,array $data,int $actor,string $key,string $requestHash): array
    {
        $this->query('INSERT INTO calculation_snapshots (trip_id,source_hash,snapshot_json,actor_id,created_at,operation_key,request_hash) VALUES (?,?,?,?,?,?,?)',[$tripId,$sourceHash,json_encode($data,JSON_THROW_ON_ERROR),$actor,gmdate(DATE_ATOM),$key,$requestHash]);
        return $this->snapshot($tripId,$key) ?? throw new \LogicException('Saved snapshot missing');
    }
    /** @return array{revision: int, data: array<array-key, mixed>}|null */
    public function draft(int $tripId): ?array
    {
        return (new SqliteTermRepository($this->pdo))->draft($tripId);
    }
    /**
     * @phpstan-impure
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
