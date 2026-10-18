<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Application\Port\{EasyVereinWriteClient,TripRepositoryInterface,AccessRepositoryInterface};
use App\Infrastructure\{TripTermBasisSync,Persistence\SqliteWorkflowRepository};
use PDO;

final class BookingBasisProvider implements EasyVereinWriteClient
{
    public array $groups=[];
    public array $writes=[];
    public int $reads=0;
    public string $description='Eigene Beschreibung';
    public bool $losePost=false;
    public function configured(): bool {return true;}
    public function writeEnabled(): bool {return true;}
    public function get(string $path,array $query=[]): array
    {
        $this->reads++;
        if($path==='price-group/')return ['results'=>array_values($this->groups),'next'=>null];
        return ['id'=>77,'prologue'=>$this->description];
    }
    public function write(string $method,string $path,array $payload): array
    {
        $this->writes[]=[$method,$path,$payload];
        if($path==='price-group/'){
            $id=count($this->groups)+100;$this->groups[$id]=['id'=>$id]+$payload;
            if($this->losePost){$this->losePost=false;throw new \RuntimeException('lost response');}
            return $this->groups[$id];
        }
        if(str_starts_with($path,'price-group/')){$id=(int)explode('/',$path)[1];$this->groups[$id]=array_replace($this->groups[$id],$payload);return $this->groups[$id];}
        $this->description=$payload['prologue'];return ['id'=>77,'prologue'=>$this->description];
    }
}
final class BookingProcessApiTest extends ApiTestCase
{
    private int $trip;
    private PDO $db;
    protected function setUp(): void
    {
        parent::setUp();$this->db=$this->app->getContainer()->get(PDO::class);
        $p=$this->validTripPayload()+['registrationMode'=>'easyverein','roomReservations'=>[['roomType'=>'2-Bettzimmer','count'=>4],['roomType'=>'4-Bettzimmer','count'=>3]]];
        $this->trip=$this->request('POST','/api/trips',$p,201)['id'];
        $this->db->prepare('INSERT INTO external_event_links(trip_id,external_id,external_json,confirmed_at) VALUES (?,\'77\',?,?)')->execute([$this->trip,json_encode(['groups'=>[['id'=>'21','label'=>'Erwachsene','price'=>'12']]]),gmdate(DATE_ATOM)]);
    }
    private function request(string $method,string $path,?array $p=null,int $status=200): array
    {
        $r=$this->dispatch($method,$path,$p);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());return json_decode((string)$r->getBody(),true);
    }
    private function payload(): array
    {
        return ['operationKey'=>'manual_booking_01','versionId'=>null,'contactIdentity'=>'Kontakt','persons'=>[['id'=>'adult','name'=>'Erwachsener','birthDate'=>'1980-01-01','priceGroupId'=>'21','expectedPrice'=>'12'],['id'=>'child','name'=>'Kind','birthDate'=>'2018-01-01','priceGroupId'=>'21','expectedPrice'=>'12']],'rooms'=>[['category'=>'2-Bettzimmer','personIds'=>['adult']],['category'=>'4-Bettzimmer','personIds'=>['child']]],'answers'=>['booking'=>[],'persons'=>[]]];
    }
    public function testMultiRoomBookingKeepsPeoplePricesAndStableIdsWhenCorrectedWithoutReason(): void
    {
        $b=$this->request('POST',"/api/trips/$this->trip/bookings",$this->payload(),201);
        self::assertSame('12',$b['persons'][0]['expectedPrice']);self::assertSame('21',$b['persons'][0]['priceGroupId']);
        self::assertSame($b['persons'][0]['id'],$b['rooms'][0]['personIds'][0]);self::assertSame($b['persons'][1]['id'],$b['rooms'][1]['personIds'][0]);
        $rooms=[['category'=>'4-Bettzimmer','personIds'=>array_map('strval',array_column($b['persons'],'id'))]];
        $c=$this->request('PUT',"/api/trips/$this->trip/bookings/{$b['id']}/answers",['operationKey'=>'correct_booking_01','revision'=>$b['revision'],'rooms'=>$rooms,'answers'=>['booking'=>[],'persons'=>[]]]);
        self::assertSame(array_column($b['persons'],'id'),array_column($c['persons'],'id'));self::assertCount(1,$c['rooms']);
        $again=$this->request('POST',"/api/trips/$this->trip/bookings",$this->payload(),201);self::assertSame($b['id'],$again['id']);
        self::assertCount(1,$this->request('GET',"/api/trips/$this->trip/registrations"));
    }
    public function testWebsiteSourceIsProjectedForExistingBookingsWithoutCreatingDuplicateRows(): void
    {
        $b=$this->request('POST',"/api/trips/$this->trip/bookings",$this->payload(),201);
        $this->db->prepare("UPDATE registrations SET owner_member_reference='11' WHERE id=?")->execute([$b['id']]);
        $rows=$this->request('GET',"/api/trips/$this->trip/registrations");self::assertCount(1,$rows);self::assertSame('website',$rows[0]['source']);
        $rows=$this->request('GET',"/api/trips/$this->trip/bookings");self::assertCount(1,$rows);self::assertSame('website',$rows[0]['source']);self::assertSame($b['id'],$rows[0]['id']);
    }
    public function testInvalidRoomAssignmentsAndTamperedPricesRollBackCompletely(): void
    {
        foreach([
            [['category'=>'Unknown','personIds'=>['adult','child']]],
            [['category'=>'2-Bettzimmer','personIds'=>['adult']]],
            [['category'=>'2-Bettzimmer','personIds'=>['adult','child']],['category'=>'4-Bettzimmer','personIds'=>['adult']]],
            [['category'=>'2-Bettzimmer','personIds'=>['adult','other']]],
        ] as $rooms){$p=$this->payload();$p['rooms']=$rooms;$this->request('POST',"/api/trips/$this->trip/bookings",$p,422);}
        $p=$this->payload();$p['persons'][0]['expectedPrice']='1';$this->request('POST',"/api/trips/$this->trip/bookings",$p,409);
        self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
    }
    public function testCancellationWithoutReasonKeepsHistoryAndRemovesParticipantsFromTripCounts(): void
    {
        $b=$this->request('POST',"/api/trips/$this->trip/bookings",$this->payload(),201);
        $c=$this->request('POST',"/api/trips/$this->trip/bookings/{$b['id']}/cancel",['operationKey'=>'cancel_booking_01','revision'=>$b['revision'],'personIds'=>array_column($b['persons'],'id')]);self::assertSame('cancelled',$c['status']);self::assertCount(2,$c['persons']);
        $list=$this->request('GET','/api/trips');self::assertSame(0,$list[0]['registeredParticipants']);
    }
    public function testBackgroundBasisSyncPreservesExtrasRecoversLostPostAndSkipsUnchangedTrips(): void
    {
        $c=$this->app->getContainer();$provider=new BookingBasisProvider();
        $provider->groups[9]=['id'=>9,'event'=>77,'name'=>'Gäste','selling_price'=>99,'description'=>'Eigener Gastpreis'];$provider->losePost=true;
        $worker=new TripTermBasisSync($c->get(SqliteWorkflowRepository::class),$c->get(TripRepositoryInterface::class),$c->get(AccessRepositoryInterface::class),$provider);
        self::assertTrue($worker->runOne());$this->db->exec('UPDATE trip_term_basis_sync SET due_at=0');self::assertTrue($worker->runOne());
        self::assertCount(4,$provider->groups);self::assertSame(99,$provider->groups[9]['selling_price']);self::assertStringContainsString('Eigene Beschreibung',$provider->description);self::assertStringContainsString('4-Bettzimmer',$provider->description);
        $reads=$provider->reads;$writes=count($provider->writes);self::assertFalse($worker->runOne());self::assertSame($reads,$provider->reads);self::assertCount($writes,$provider->writes);
    }
}
