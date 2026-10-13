<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Application\AccessException;
use App\Application\ActualExpenseService;
use App\Application\AuthService;
use App\Application\Port\TripRepositoryInterface;
use App\Application\AccessPolicy;
use App\Infrastructure\Persistence\SqliteSchema;
use PDO;
use Symfony\Component\Yaml\Yaml;

final class EventAccessApiTest extends ApiTestCase
{
    private function json(string $method, string $path, ?array $payload = null, int $status = 200): array
    {
        $response = $this->dispatch($method, $path, $payload);
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
    private function person(string $email = 'orga@example.com', string $role = 'user'): int
    {
        $pdo = $this->app->getContainer()->get(PDO::class);
        $pdo->prepare('INSERT INTO users(email,role,created_at) VALUES(?,?,?)')->execute([$email,$role,gmdate(DATE_ATOM)]);
        return (int) $pdo->lastInsertId();
    }
    private function trip(?int $eventId = null): int
    {
        $p = $this->validTripPayload(); if ($eventId !== null) { $p['eventId'] = $eventId; }
        return $this->json('POST','/api/trips',$p,201)['id'];
    }
    private function event(): int { return $this->json('POST','/api/events',['name'=>'Winterfreizeit','description'=>'Fiktiver Test'],201)['id']; }
    private function grant(int $trip, int $user, array $caps): void
    {
        $this->json('PUT',"/api/trips/$trip/team/$user",['capabilities'=>$caps,'reason'=>'Testzuordnung']);
    }
    private function login(int $id, string $snapshotRole = 'user'): void { $_SESSION['user_id']=$id; $_SESSION['user_role']=$snapshotRole; }

    public function testUnassignedUserCannotSeeTripOrGuessChildResource(): void
    {
        $a=$this->trip(); $b=$this->trip();
        $expense=$this->json('POST',"/api/trips/$b/actual-expenses",['label'=>'Fiktiv','amount'=>7],201);
        $user=$this->person(); $this->grant($a,$user,['trip.view','expense.manage']); $this->login($user);
        self::assertSame([$a],array_column($this->json('GET','/api/trips'),'id'));
        $this->json('GET',"/api/trips/$b",null,404);
        $this->json('PUT',"/api/trips/$a/actual-expenses/{$expense['id']}",['label'=>'Versuch','amount'=>99],404);
        $this->json('DELETE',"/api/trips/$a/actual-expenses/{$expense['id']}",null,404);
        $this->login(1,'admin');
        self::assertSame(7, $this->json('GET',"/api/trips/$b/actual-expenses")[0]['amount']);
    }
    public function testRevokeTakesEffectInExistingSessionAndAdminHasNoImplicitTripRights(): void
    {
        $trip=$this->trip(); $user=$this->person('second-admin@example.com','admin');
        $this->login($user,'admin'); $this->json('GET',"/api/trips/$trip",null,404);
        $this->login(1,'admin'); $this->grant($trip,$user,['trip.view']);
        $this->login($user,'admin'); $this->json('GET',"/api/trips/$trip");
        $this->app->getContainer()->get(PDO::class)->prepare('DELETE FROM trip_access WHERE trip_id=? AND user_id=?')->execute([$trip,$user]);
        $this->json('GET',"/api/trips/$trip",null,404);
    }
    public function testSettlementTeamCanReadFinanceButNotRegistrationsOrWrite(): void
    {
        $trip=$this->trip();
        $this->json('POST',"/api/trips/$trip/registrations",['roomCategory'=>'2-Bettzimmer','participants'=>[['name'=>'Testperson','birthDate'=>'1985-01-01']]],201);
        $expected=$this->json('GET',"/api/trips/$trip/settlement")['surplus'];
        $user=$this->person(); $this->grant($trip,$user,['trip.view','settlement.view','expense.view']); $this->login($user);
        self::assertSame($expected,$this->json('GET','/api/trips')[0]['surplus']);
        self::assertNull($this->json('GET','/api/trips')[0]['registeredParticipants']);
        $this->json('GET',"/api/trips/$trip/registrations",null,403);
        $this->json('POST',"/api/trips/$trip/calculate",null,403);
        $this->json('POST',"/api/trips/$trip/actual-expenses",['label'=>'Nein','amount'=>7],403);
        $this->json('GET',"/api/trips/$trip/settlement");
    }
    public function testTermOnlyTeamDoesNotReceiveFinancialValues(): void
    {
        $trip=$this->trip(); $user=$this->person(); $this->grant($trip,$user,['trip.view','term.manage']); $this->login($user);
        $data=$this->json('GET',"/api/trips/$trip"); self::assertArrayNotHasKey('bookings',$data); self::assertArrayNotHasKey('markupPercent',$data);
        $row=$this->json('GET','/api/trips')[0]; self::assertNull($row['surplus']); self::assertNull($row['plannedParticipants']);
    }
    public function testClosureFreezesSettlementAndBlocksEveryFinancialWrite(): void
    {
        $trip=$this->trip(); $expense=$this->json('POST',"/api/trips/$trip/actual-expenses",['label'=>'Bus','amount'=>5],201);
        $before=$this->json('GET',"/api/trips/$trip/settlement");
        $this->json('POST',"/api/trips/$trip/close",['reason'=>'Abnahme Test']);
        foreach ([['PUT',"/api/trips/$trip",$this->validTripPayload()],['POST',"/api/trips/$trip/calculate",null],['POST',"/api/trips/$trip/registrations",[]],['POST',"/api/trips/$trip/registrations/import",['csv'=>'x']],['POST',"/api/trips/$trip/registrations/recalculate",null],['DELETE',"/api/trips/$trip/registrations",null],['POST',"/api/trips/$trip/actual-expenses",['label'=>'Neu','amount'=>9]],['PUT',"/api/trips/$trip/actual-expenses/{$expense['id']}",['label'=>'Neu','amount'=>9]],['DELETE',"/api/trips/$trip/actual-expenses/{$expense['id']}",null]] as [$method,$path,$p]) {
            $this->json($method,$path,$p,409);
        }
        self::assertSame($before,$this->json('GET',"/api/trips/$trip/settlement"));
        self::assertNotNull($this->json('GET',"/api/trips/$trip")['calculationSnapshot']);
        $this->json('POST',"/api/trips/$trip/reopen",['reason'=>''],422);
        $this->json('POST',"/api/trips/$trip/reopen",['reason'=>'Korrektur Test']);
        $this->json('POST',"/api/trips/$trip/calculate");
        $rows=$this->app->getContainer()->get(PDO::class)->query("SELECT action, reason, correlation_id FROM audit_events WHERE action LIKE 'settlement.%'")->fetchAll();
        self::assertSame(['settlement.close','settlement.reopen'],array_column($rows,'action')); self::assertNotEmpty($rows[0]['correlation_id']);
    }
    public function testApplicationServiceCannotBypassClosure(): void
    {
        $trip=$this->trip(); $this->json('POST',"/api/trips/$trip/close",['reason'=>'Fertig']);
        $this->expectException(AccessException::class);
        $this->app->getContainer()->get(ActualExpenseService::class)->addExpense($trip,['label'=>'Direkter Worker','amount'=>10]);
    }
    public function testEventTeamIsAnIndependentCopyAndNoOtherTripsLeakIntoCounters(): void
    {
        $event=$this->event(); $user=$this->person();
        $this->json('PUT',"/api/events/$event/default-team/$user",['capabilities'=>['trip.view','settlement.view'],'reason'=>'Vorlage']);
        $first=$this->trip($event);
        $this->json('PUT',"/api/events/$event/default-team/$user",['capabilities'=>[],'reason'=>'Neue Vorlage']);
        $second=$this->trip($event);
        $this->login($user);
        $events=$this->json('GET','/api/events'); self::assertSame(1,$events[0]['tripCount']);
        $detail=$this->json('GET',"/api/events/$event"); self::assertSame([$first],array_column($detail['trips'],'id'));
        $this->json('GET',"/api/trips/$second",null,404);
        $this->json('GET',"/api/trips/$first");
    }
    public function testEventManagementDoesNotGrantAnyTripAccess(): void
    {
        $event=$this->event(); $trip=$this->trip($event); $user=$this->person();
        $this->json('PUT',"/api/events/$event/access/$user",['capabilities'=>['event.view','event.manage'],'reason'=>'Verwaltung']);
        $this->login($user);
        self::assertSame([],$this->json('GET',"/api/events/$event")['trips']);
        $this->json('GET',"/api/trips/$trip",null,404);
    }
    public function testArchiveAndRevisionConflictKeepHistoricalTrips(): void
    {
        $event=$this->event(); $trip=$this->trip($event);
        $this->json('PUT',"/api/events/$event",['name'=>'Winterfreizeit','archived'=>true,'revision'=>1]);
        $this->json('POST','/api/trips',$this->validTripPayload()+['eventId'=>$event],409);
        $this->json('PUT',"/api/events/$event",['name'=>'Veraltet','revision'=>1],409);
        self::assertSame([],$this->json('GET','/api/events'));
        self::assertSame($event,$this->json('GET','/api/events?archived=true')[0]['id']);
        $this->json('GET',"/api/trips/$trip");
        $this->json('PUT',"/api/events/$event",['name'=>'Winterfreizeit','archived'=>false,'revision'=>2]);
        $this->trip($event);
    }
    public function testDuplicateCopiesOnlyPlanningAndCurrentTemplate(): void
    {
        $event=$this->event(); $trip=$this->trip($event);
        $this->json('POST',"/api/trips/$trip/registrations",['roomCategory'=>'2-Bettzimmer','participants'=>[['name'=>'Testperson','birthDate'=>'1985-01-01']]],201);
        $this->json('POST',"/api/trips/$trip/actual-expenses",['label'=>'Fiktiv','amount'=>1],201);
        $this->json('POST',"/api/trips/$trip/close",['reason'=>'Abgeschlossen']);
        $id=$this->json('POST',"/api/trips/$trip/duplicate",['name'=>'Neue Durchführung','startDate'=>'2028-01-01','endDate'=>'2028-01-01'],201)['id'];
        $copy=$this->json('GET',"/api/trips/$id"); self::assertNull($copy['closedAt']); self::assertSame($event,$copy['eventId']); self::assertSame($this->json('GET',"/api/trips/$trip")['bookings'],$copy['bookings']);
        self::assertSame([],$this->json('GET',"/api/trips/$id/registrations")); self::assertSame([],$this->json('GET',"/api/trips/$id/actual-expenses"));
    }
    public function testDayTripAndLegacyAssignmentPreserveExistingValues(): void
    {
        $event=$this->event(); $p=$this->validTripPayload(); $p['endDate']=$p['startDate'];
        foreach ($p['bookings'] as &$booking) { $booking['basePricePerPerson']=0; } unset($booking);
        $id=$this->json('POST','/api/trips',$p,201)['id'];
        $before=$this->json('GET',"/api/trips/$id"); $this->json('PUT',"/api/trips/$id/event",['eventId'=>$event,'reason'=>'Gezielte Zuordnung']);
        $after=$this->json('GET',"/api/trips/$id"); self::assertSame($before['bookings'],$after['bookings']); self::assertSame($event,$after['eventId']);
        self::assertSame(0,$this->json('GET',"/api/events/$event")['trips'][0]['nights']);
    }
    public function testStaleAdminSessionCannotModifyGlobalSettingsOrListUsers(): void
    {
        $user=$this->person(); $this->login($user,'admin');
        $this->json('PUT','/api/settings',[],403); $this->json('GET','/api/users',null,403); $this->json('POST','/api/auth/invite',['email'=>'nobody@example.com'],403);
    }
    public function testDelegationRequiresHeldCapabilitiesAndEmptyReasonIsRejected(): void
    {
        $trip=$this->trip(); $user=$this->person(); $third=$this->person('third@example.com');
        $this->grant($trip,$user,['trip.view','access.manage']); $this->login($user);
        $this->json('PUT',"/api/trips/$trip/team/$third",['capabilities'=>['settlement.view'],'reason'=>'Nicht erlaubt'],403);
        $this->json('PUT',"/api/trips/$trip/team/$third",['capabilities'=>['trip.view'],'reason'=>''],422);
    }
    public function testNewApiContractAndDeepLinks(): void
    {
        $spec=Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');
        foreach (['/api/events','/api/events/{id}','/api/events/{id}/default-team','/api/trips/{id}/team','/api/trips/{id}/close','/api/trips/{id}/reopen','/api/trips/{id}/duplicate','/api/admin/legacy-access'] as $path) { self::assertArrayHasKey($path,$spec['paths']); }
        $event=$this->event(); $trip=$this->trip($event);
        foreach (["/events","/events/$event","/trips/$trip"] as $path) { $response=$this->dispatch('GET',$path); self::assertSame(200,$response->getStatusCode()); self::assertStringContainsString('management.js',(string)$response->getBody()); }
    }
    public function testTripWithoutEventCanBeSavedAndAssignedLater(): void
    {
        $payload=$this->validTripPayload()+['eventId'=>null];
        $trip=$this->json('POST','/api/trips',$payload,201);
        self::assertNull($trip['eventId']);
        $event=$this->event();
        $this->json('PUT',"/api/trips/{$trip['id']}/event",['eventId'=>$event,'reason'=>'Nachträgliche Zuordnung']);
        $assigned=$this->json('GET',"/api/trips/{$trip['id']}");
        self::assertSame($event,$assigned['eventId']);
        self::assertSame($trip['bookings'],$assigned['bookings']);
        self::assertSame($trip['name'],$assigned['name']);
    }
    public function testInvalidEventReferenceReturnsFieldErrorInsteadOfAccessError(): void
    {
        foreach ([0,-1,'',false,[],'1'] as $invalid) {
            $response=$this->json('POST','/api/trips',$this->validTripPayload()+['eventId'=>$invalid],422);
            self::assertArrayHasKey('eventId',$response['details']);
        }
    }

}
