<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{AccessRepositoryInterface, ActorInterface, BookingRepositoryInterface, ParticipationPort};
use App\Application\Port\ParticipationRepositoryInterface;

/** Read-side provider polling under one explicitly configured Calculator identity. */
final readonly class ScheduledReconciliation
{
    public function __construct(private ParticipationRepositoryInterface $store, private AccessRepositoryInterface $access, private ParticipationPort $external, private BookingRepositoryInterface $bookings) {}
    public function runOne(int $actorId, int $interval=300): bool
    {
        if ($actorId<1 || $this->external->blockedReason()!==null || $this->access->role($actorId)===null) { return false; }
        $trip=$this->access->transaction(function() use($actorId,$interval) {
            $row=$this->store->nextScheduledTrip($actorId, time()-max(60,$interval));
            if($row===false){return null;}
            $this->store->recordScheduledAttempt($row, time());
            return (int)$row;
        });
        if($trip===null){return false;}
        $actor=new class($actorId) implements ActorInterface {
            public function __construct(private readonly int $id) {}
            public function userId(): int {return $this->id;}
        };
        $policy=new AccessPolicy($this->access,$actor);
        $service=new ParticipationService($policy,$this->store,$this->external,$this->bookings,$this->access);
        try {$service->reconcile($trip);}
        catch(\Throwable){return true;} // Next bounded poll; do not log member data or provider bodies.
        return true;
    }
}
