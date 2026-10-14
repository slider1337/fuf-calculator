<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{AccessRepositoryInterface, ActorInterface, BookingRepositoryInterface, ParticipationPort};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;

/** Read-side provider polling under one explicitly configured Calculator identity. */
final readonly class ScheduledReconciliation
{
    public function __construct(private SqliteWorkflowRepository $store, private AccessRepositoryInterface $access, private ParticipationPort $external, private BookingRepositoryInterface $bookings) {}
    public function runOne(int $actorId, int $interval=300): bool
    {
        if ($actorId<1 || $this->external->blockedReason()!==null || $this->access->role($actorId)===null) { return false; }
        $trip=$this->access->transaction(function() use($actorId,$interval) {
            $row=$this->store->query("SELECT l.trip_id FROM external_event_links l JOIN trips t ON t.id=l.trip_id JOIN trip_access a ON a.trip_id=l.trip_id LEFT JOIN reconciliation_schedule s ON s.trip_id=l.trip_id WHERE a.user_id=? AND a.capability='term.manage' AND t.registration_mode='easyverein' AND t.archived_at IS NULL AND (s.attempted_at IS NULL OR s.attempted_at<=?) ORDER BY COALESCE(s.attempted_at,0),l.trip_id LIMIT 1",[$actorId,time()-max(60,$interval)])->fetchColumn();
            if($row===false){return null;}
            $this->store->query('INSERT INTO reconciliation_schedule (trip_id,attempted_at) VALUES (?,?) ON CONFLICT(trip_id) DO UPDATE SET attempted_at=excluded.attempted_at',[$row,time()]);
            return (int)$row;
        });
        if($trip===null){return false;}
        $actor=new class($actorId) implements ActorInterface {
            public function __construct(private readonly int $id) {}
            public function userId(): ?int {return $this->id;}
        };
        $policy=new AccessPolicy($this->access,$actor);
        $service=new ParticipationService($policy,$this->store,$this->external,$this->bookings,$this->access);
        try {$service->reconcile($trip);}
        catch(\Throwable){return true;} // Next bounded poll; do not log member data or provider bodies.
        return true;
    }
}
