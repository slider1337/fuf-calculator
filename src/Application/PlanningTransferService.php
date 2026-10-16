<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{TripRepositoryInterface,BookingRepositoryInterface,FormRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Trip\{Trip,RoomBooking};
use App\Domain\Form\Decimal;

/**
 * @phpstan-import-type TripView from TripData
 * @phpstan-import-type Booking from \App\Application\Port\BookingRepositoryInterface
 * @phpstan-import-type FormBinding from \App\Application\Port\FormRepositoryInterface
 * @phpstan-import-type MetricCounts from AnalyticsService
 * @phpstan-type PlanningSource array{transferRevision:int,planning:array<string,mixed>,binding:FormBinding,bookings:list<Booking>}
 * @phpstan-type PlanningPreview array{sourceHash:string,roomMapping:array<string,"ADULT_DOUBLE"|"ADULT_MULTI">,sourceStand:string,before:TripView,after:TripView,budgetBefore:array<string,mixed>,budgetAfter:array<string,mixed>,quantities:list<array{fieldId:string,target:string,value:string,counts:MetricCounts}>,quantityTotals:array{vegetarianMeals:string},assignedRooms:list<int|string>,excludedPersons:int,issues:list<string>,canApply:bool,note:string}
 */
final readonly class PlanningTransferService
{
    public function __construct(private AccessPolicy $policy,private TripRepositoryInterface $trips,private BookingRepositoryInterface $bookings,private FormRepositoryInterface $forms,private AnalyticsService $analytics,private PriceCalculatorService $calculator,private SqliteWorkflowRepository $store) {}
    private function permissions(int $id,bool $write=false): void
    {
        $this->policy->trip($id,'calculation.manage',$write);
        if($write){$this->policy->requireRegistrationMode($id,'easyverein');}
        $this->policy->trip($id,'registration.view');
        $this->policy->trip($id,'answers.view');
        $this->policy->trip($id,'analytics.view');
    }
    /** @return PlanningSource */
    private function source(int $id): array
    {
        $trip=$this->trips->getById($id)??throw new NotFoundException('Trip not found');
        return ['transferRevision'=>$this->store->transferRevision($id),'planning'=>TripData::fromTrip($trip)+['plannedTotalCosts'=>$trip->plannedTotalCosts(),'plannedTotalRevenue'=>$trip->plannedTotalRevenue()], 'binding'=>$this->forms->binding($id),'bookings'=>$this->bookings->list($id)];
    }
    /** @param array<array-key,mixed> $data */
    public static function hash(array $data): string {return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));}
    /** @param array<array-key,mixed> $p */
    public static function operationKey(array $p): string
    {
        $key=$p['operationKey']??null;
        if(!is_string($key)||!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$key)){throw new ValidationException(['operationKey'=>'8_100_safe_characters']);}
        return $key;
    }
    /** @return array<array-key,mixed> */
    public function status(int $id): array
    {
        $this->permissions($id);
        return $this->bookings->snapshot(function() use($id){$s=$this->store->snapshot($id);return ['snapshot'=>$s===null?null:array_diff_key($s,['requestHash'=>true]),'stale'=>$s!==null&&!hash_equals($s['sourceHash'],self::hash($this->source($id)))];});
    }
    /**
     * @param array<array-key,mixed> $p
     * @return PlanningPreview
     */
    public function preview(int $id,array $p): array
    {
        $this->permissions($id,true);
        return $this->bookings->snapshot(fn()=>$this->build($id,$p));
    }
    /**
     * @param array<array-key,mixed> $p
     * @return PlanningPreview
     */
    private function build(int $id,array $p): array
    {
        $mapping=$p['roomMapping']??[];
        if(!is_array($mapping)){throw new ValidationException(['roomMapping'=>'object_required']);}
        foreach($mapping as $room=>$category){if(!is_string($room)||trim($room)===''||!in_array($category,['ADULT_DOUBLE','ADULT_MULTI'],true)){throw new ValidationException(['roomMapping'=>'assigned_room_to_adult_category_required']);}}
        $source=$this->source($id);$trip=$this->trips->getById($id) ?? throw new NotFoundException('Trip');$counts=['ADULT_DOUBLE'=>0,'ADULT_MULTI'=>0,'CHILD'=>0];$rooms=[];$issues=[];$excluded=0;$spa=0;
        foreach($source['bookings'] as $b){foreach($b['persons'] as $person){
            if($person['status']!=='confirmed'){$excluded++;continue;}
            if(!\App\Domain\Form\FormSchema::date($person['birthDate'])||$person['birthDate']>$trip->startDate()->format('Y-m-d')){$issues[]='Geburtsdatum prüfen: '.$b['reference'];continue;}
            $age=(new \DateTimeImmutable($person['birthDate']))->diff($trip->startDate())->y;
            if($age>=$trip->pricingPolicy()->spaTaxAgeThreshold()){$spa++;}
            $room=trim($b['roomAssignment'] ?? '');
            if($room!==''){$rooms[$room]=true;}
            if($age<$trip->pricingPolicy()->adultAgeThreshold()){$counts['CHILD']++;continue;}
            if($trip->nights()===0){$counts['ADULT_MULTI']++;continue;}
            if($room===''||!isset($mapping[$room])){$issues[]='Zimmerzuteilung / Kalkulationskategorie fehlt: '.$b['reference'];continue;}
            $counts[$mapping[$room]]++;
        }}
        // Only compatible, explicitly mapped quantities; never derive prices from arbitrary answers.
        $quantities=[];$fieldIds=[];
        foreach($source['bookings'] as $b){if($b['versionId']===null){continue;}$version=$this->forms->get($b['versionId'])??throw new NotFoundException('Form version');foreach($version['fields'] as $field){if($field['mapping']==='vegetarianMeals'){$fieldIds[$field['id']]=true;}}}
        $bindingVersion=$source['binding']['versionId']??null;
        if($bindingVersion!==null){foreach(($this->forms->get($bindingVersion)??throw new NotFoundException('Form version'))['fields'] as $field){if($field['mapping']==='vegetarianMeals'){$fieldIds[$field['id']]=true;}}}
        if($fieldIds!==[]){foreach($source['bookings'] as $b){
            if(!array_filter($b['persons'],static fn($person)=>$person['status']==='confirmed')){continue;}
            $own=$b['versionId']===null?[]:($this->forms->get($b['versionId'])??throw new NotFoundException('Form version'))['fields'];
            if(!array_filter($own,static fn($field)=>$field['mapping']==='vegetarianMeals')){$issues[]='Zuordnungsmenge in älterer Buchung nicht abgefragt: '.$b['reference'];}
        }}
        foreach(array_keys($fieldIds) as $fid){
            $data=$this->analytics->read([$id],['fieldId'=>$fid]);$m=$data['metrics'][0] ?? throw new \LogicException('Mapped field metric missing');
            if($m['counts']['missing']||$m['counts']['unchecked']){$issues[]='Zuordnungsfeld unvollständig / ungeprüft: '.$m['field']['label'];}
            $value=$m['field']['type']==='checkbox'?(string)$m['yes']:($m['sum'] ?? throw new \LogicException('Mapped field is not a quantity'));
            if(Decimal::compare($value,'0')<0||Decimal::compare($value,'1000000')>0){$issues[]='Zuordnungsmenge außerhalb des Planungsbereichs';}
            $quantities[]=['fieldId'=>$fid,'target'=>'vegetarianMeals','value'=>$value,'counts'=>$m['counts']];
        }
        $newBookings=array_map(static fn($b)=>new RoomBooking($b->categoryType(),$counts[$b->categoryType()->value],$b->basePricePerPerson(),$b->salesPricePerPerson()),$trip->bookings());
        $next=new Trip($id,$trip->name(),$trip->startDate(),$trip->pricingPolicy(),$newBookings,$trip->groupExpenses(),$trip->plannedTotalCosts(),$trip->plannedTotalRevenue(),$spa,$trip->endDate(),$trip->roomReservations(),$trip->eventId());
        $after=$this->calculator->calculate($next);
        return ['sourceHash'=>self::hash($source),'roomMapping'=>$mapping,'sourceStand'=>gmdate(DATE_ATOM),'before'=>TripData::fromTrip($trip),'after'=>TripData::fromTrip($next),'budgetBefore'=>$this->calculator->calculate($trip),'budgetAfter'=>$after,'quantities'=>$quantities,'quantityTotals'=>['vegetarianMeals'=>Decimal::sum(array_column($quantities,'value'))],'assignedRooms'=>array_keys($rooms),'excludedPersons'=>$excluded,'issues'=>array_values(array_unique($issues)),'canApply'=>$issues===[],'note'=>'Reservierte Zimmer bleiben Vertragsbestand. Zusatzmengen werden als Planungsstand gespeichert, ohne automatische Preiswirkung. Bestehende Buchungspreise bleiben erhalten.'];
    }
    /**
     * @param array<array-key,mixed> $p
     * @return array<array-key,mixed>
     */
    public function apply(int $id,array $p): array
    {
        return $this->policy->writeTrip($id,'calculation.manage',function() use($id,$p){
            $this->permissions($id,true);$key=self::operationKey($p);$requestHash=self::hash($p);
            if($old=$this->store->snapshot($id,$key)){if(!hash_equals($old['requestHash'],$requestHash)){throw new AccessException('idempotency_conflict',409);}return $old;}
            $reason=$this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');$preview=$this->build($id,$p);
            if(!is_string($p['sourceHash']??null)||!hash_equals($preview['sourceHash'],$p['sourceHash'])){throw new AccessException('source_changed_preview_again',409);}
            if(!$preview['canApply']){throw new ValidationException(['planning'=>implode('; ',$preview['issues'])]);}
            $trip=$this->trips->getById($id) ?? throw new NotFoundException('Trip');$payload=$preview['after'];$counts=array_column($payload['bookings'],'count','categoryType');
            $next=new Trip($id,$trip->name(),$trip->startDate(),$trip->pricingPolicy(),array_map(static fn($b)=>new RoomBooking($b->categoryType(),$counts[$b->categoryType()->value],$b->basePricePerPerson(),$b->salesPricePerPerson()),$trip->bookings()),$trip->groupExpenses(),$trip->plannedTotalCosts(),$trip->plannedTotalRevenue(),$payload['spaTaxCount'],$trip->endDate(),$trip->roomReservations(),$trip->eventId());
            $this->trips->update($next);
            $this->store->advanceTransferRevision($id);
            $preview['reason']=$reason;
            $snapshot=$this->store->saveSnapshot($id,self::hash($this->source($id)),$preview,$this->policy->actorId(),$key,$requestHash);
            $this->policy->audit('calculation.transfer','trip',$id,$reason.' · snapshot='.$snapshot['id']);
            return $snapshot;
        });
    }
}
