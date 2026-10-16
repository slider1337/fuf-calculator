<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{BookingRepositoryInterface,FormRepositoryInterface,TripRepositoryInterface};
use App\Domain\Form\{FormSchema,Decimal,AnswerValidator};

/**
 * One row per booking field or person field, shared by summaries, drilldown and exports.
 * @phpstan-import-type Field from \App\Domain\Form\FormSchema
 * @phpstan-type AnalyticsRow array{tripId:int,bookingId:int,reference:string,participantId:?int,person:?string,status:string,syncStatus:string,source:string,versionId:?int,fieldId:string,label:string,type:string,scope:string,unit:string,state:string,value:mixed,answerAt:?string,revisionId?:int,actorId?:int,reason?:string,revisionState?:string,revisionValue?:mixed,revisionAt?:string,revisionVersionId?:int}
 * @phpstan-type MetricCounts array{answered:int,missing:int,unchecked:int,not_applicable:int,not_asked:int}
 * @phpstan-type Metric array{field:Field,counts:MetricCounts,denominator:int,sum:?string,uncheckedSum:?string,yes:int,no:int,options:list<array{id:string,label:string,count:int}>}
 * @phpstan-type AnalyticsData array{stand:string,filters:array{tripIds:list<int>,status:string,versionId:?int,fieldId:?string,state:?string,search:string},populationLabel:string,totalLabel:string,versions:list<int>,metrics:list<Metric>,tripMetrics:list<array{tripId:int,metrics:list<Metric>}>,rows:list<AnalyticsRow>,revisionRows:list<AnalyticsRow>}
 */
final readonly class AnalyticsService
{
    public function __construct(private BookingRepositoryInterface $bookings,private FormRepositoryInterface $forms,private AccessPolicy $policy,private TripRepositoryInterface $trips,private AnswerValidator $validator) {}
    /**
     * @param list<int> $tripIds
     * @param array<array-key,mixed> $q
     * @return AnalyticsData
     */
    public function read(array $tripIds,array $q=[],bool $export=false): array
    {
        return $this->bookings->snapshot(fn()=>$this->snapshot($tripIds,$q,$export));
    }
    /**
     * @param list<int> $tripIds
     * @param array<array-key,mixed> $q
     * @return AnalyticsData
     */
    private function snapshot(array $tripIds,array $q,bool $export): array
    {
        $status=$q['status']??'confirmed';$version=$q['versionId']??null;$field=$q['fieldId']??null;$state=$q['state']??null;$search=$q['search']??'';
        if(!in_array($status,['confirmed','all','pending','cancelled'],true)||($version!==null&&(!is_scalar($version)||!ctype_digit((string)$version)))||($field!==null&&(!is_string($field)||!preg_match('/^f_[a-f0-9]{32}$/D',$field)))||($state!==null&&!in_array($state,['answered','missing','unchecked','not_applicable','not_asked'],true))||!is_string($search)||mb_strlen($search)>200){throw new ValidationException(['filters'=>'invalid_filter']);}
        $catalog=[];$bindings=[];
        foreach($tripIds as $t){
            $this->policy->trip($t,'analytics.view');$this->policy->trip($t,'answers.view');if($export){$this->policy->trip($t,'answers.export');}
            $bindings[$t]=$this->forms->binding($t);
            $eid=$this->policy->metadata($t)['event_id']??null;
            foreach($eid===null?[]:$this->forms->list($eid) as $v){if($v['status']==='draft'){continue;}foreach($v['fields'] as $f){if(!FormSchema::answerField($f)){continue;}if(isset($catalog[$f['id']])&&FormSchema::signature($catalog[$f['id']])!==FormSchema::signature($f)){throw new AccessException('incompatible_field_series',409);}$catalog[$f['id']]=$f;}}
        }
        if($field!==null&&!isset($catalog[$field])){throw new ValidationException(['fieldId'=>'unknown_field_series']);}
        if($field!==null){$catalog=[$field=>$catalog[$field]];}else{$catalog=array_filter($catalog,static fn($f)=>$f['analytics']===true);}
        $rows=[];$revisionRows=[];$versionIds=[];
        foreach($tripIds as $t){foreach($this->bookings->list($t) as $b){
            if($version!==null&&($b['versionId']??0)!==(int)$version){continue;}
            $versionIds[$b['versionId']??0]=true;
            $persons=array_values(array_filter($b['persons'],static fn($p)=>match($status){'confirmed'=>$p['status']==='confirmed','pending'=>$p['status']==='pending','cancelled'=>$p['status']==='cancelled',default=>true}));
            if($persons===[]){continue;}
            $own=$b['versionId']===null?null:$this->forms->get($b['versionId']);$ownFields=array_column($own['fields']??[],null,'id');$current=[];
            foreach($b['answers'] as $a){$current[($a['participantId']??'booking').':'.$a['fieldId']]=$a;}
            $applicability=[];
            if($own!==null){
                $raw=['booking'=>[],'persons'=>[]];
                foreach($b['answers'] as $a){if(!isset($ownFields[$a['fieldId']])||!in_array($a['state'],['answered','unchecked'],true)){continue;}if($a['participantId']===null){$raw['booking'][$a['fieldId']]=$a['value'];}elseif(in_array($a['participantId'],array_column($persons,'id'),true)){$raw['persons'][(string)$a['participantId']][$a['fieldId']]=$a['value'];}}
                $trip=$this->trips->getById($t) ?? throw new NotFoundException('Trip');
                $applicability=$this->validator->validate($own['fields'],['startDate'=>$trip->startDate()->format('Y-m-d'),'adultAgeThreshold'=>$trip->pricingPolicy()->adultAgeThreshold(),'persons'=>array_map(static fn($p)=>['id'=>(string)$p['id'],'birthDate'=>$p['birthDate']],$persons)],$raw)['answers'];
            }
            foreach($catalog as $fid=>$f){
                foreach($f['scope']==='booking'?[null]:$persons as $person){
                    $pid=$person['id']??null;$a=$current[($pid??'booking').':'.$fid]??null;
                    $app=$pid===null?($applicability['booking'][$fid]??null):($applicability['persons'][(string)$pid][$fid]??null);
                    $s=!isset($ownFields[$fid])?'not_asked':(($app['state']??null)==='not_applicable'?'not_applicable':($a['state']??'missing')); 
                    $r=['tripId'=>$t,'bookingId'=>$b['id'],'reference'=>$b['reference'],'participantId'=>$pid,'person'=>$person['name']??$b['contactIdentity'],'status'=>$person===null?$b['status']:$person['status'],'syncStatus'=>$person===null?$b['syncStatus']:$person['syncStatus'],'source'=>$b['source'],'versionId'=>$b['versionId'],'fieldId'=>$fid,'label'=>$f['label'],'type'=>$f['type'],'scope'=>$f['scope'],'unit'=>$f['unit'],'state'=>$s,'value'=>in_array($s,['answered','unchecked'],true)?($a['value']??null):null,'answerAt'=>$a['createdAt']??null];
                    if($state!==null&&$s!==$state){continue;}
                    if($search!==''&&!str_contains(mb_strtolower($r['person'].' '.json_encode($r['value'],JSON_UNESCAPED_UNICODE)),mb_strtolower($search))){continue;}
                    $rows[]=$r;
                    foreach($b['revisions'] as $rev){if($rev['fieldId']===$fid&&$rev['participantId']===$pid){$revisionRows[]=$r+['revisionId'=>$rev['id'],'actorId'=>$rev['actorId'],'reason'=>$rev['reason'],'revisionState'=>$rev['state'],'revisionValue'=>$rev['value'],'revisionAt'=>$rev['createdAt'],'revisionVersionId'=>$rev['versionId']];}}
                }
            }
        }}
        $metrics=[];$tripMetrics=[];
        foreach($catalog as $fid=>$f){$selected=array_values(array_filter($rows,static fn($r)=>$r['fieldId']===$fid));$metrics[]=$this->metric($f,$selected);foreach($tripIds as $t){$tripMetrics[$t][]=$this->metric($f,array_values(array_filter($selected,static fn($r)=>$r['tripId']===$t)));}}
        return ['stand'=>gmdate(DATE_ATOM),'filters'=>['tripIds'=>$tripIds,'status'=>$status,'versionId'=>$version===null?null:(int)$version,'fieldId'=>$field,'state'=>$state,'search'=>$search],'populationLabel'=>$status==='confirmed'?'Bestätigte aktive Personen / Buchungen mit mindestens einer bestätigten aktiven Person':'Alternative Grundgesamtheit – nicht die reguläre Summe','totalLabel'=>'Gesamt über ausgewählte Reisen: Reise-Teilnahmen, keine eindeutigen Mitglieder','versions'=>array_map('intval',array_keys($versionIds)),'metrics'=>$metrics,'tripMetrics'=>array_map(static fn($t)=>['tripId'=>$t,'metrics'=>$tripMetrics[$t]??[]],$tripIds),'rows'=>$rows,'revisionRows'=>$revisionRows];
    }
    /**
     * @param Field $f
     * @param list<AnalyticsRow> $rows
     * @return Metric
     */
    private function metric(array $f,array $rows): array
    {
        $counts=array_fill_keys(['answered','missing','unchecked','not_applicable','not_asked'],0);$values=[];$unchecked=[];$yes=0;$no=0;$options=[];
        foreach($f['options'] as $o){$options[$o['id']]=['id'=>$o['id'],'label'=>$o['label'],'count'=>0];}
        foreach($rows as $r){if(!array_key_exists($r['state'],$counts))throw new \UnexpectedValueException('Invalid answer state');$counts[$r['state']]++;if($r['state']==='unchecked'&&$f['type']==='number'){$unchecked[]=Decimal::normalize($r['value']) ?? throw new \UnexpectedValueException('Invalid numeric answer');}if($r['state']!=='answered'){continue;}if($f['type']==='number'){$values[]=Decimal::normalize($r['value']) ?? throw new \UnexpectedValueException('Invalid numeric answer');}if($r['value']===true){$yes++;}if($r['value']===false){$no++;}if(in_array($f['type'],['single_select','multi_select'],true)){foreach(is_array($r['value'])?$r['value']:[$r['value']] as $v){if((is_string($v)||is_int($v))&&isset($options[$v])){$options[$v]['count']++;}}}}
        return ['field'=>$f,'counts'=>$counts,'denominator'=>$counts['answered']+$counts['missing']+$counts['unchecked'],'sum'=>$f['type']==='number'?Decimal::sum($values):null,'uncheckedSum'=>$f['type']==='number'?Decimal::sum($unchecked):null,'yes'=>$yes,'no'=>$no,'options'=>array_values($options)];
    }
    /**
     * @param AnalyticsData $data
     * @param array<array-key,mixed> $q
     * @return array<array-key,mixed>
     */
    public function page(array $data,array $q): array
    {
        $page=self::pageNumber($q['page']??1,1000000,'page');
        $pageSize=self::pageNumber($q['pageSize']??50,200,'pageSize');
        $total=count($data['rows']);$data['rows']=array_slice($data['rows'],($page-1)*$pageSize,$pageSize);unset($data['revisionRows']);return $data+['page'=>$page,'pageSize'=>$pageSize,'totalRows'=>$total];
    }
    /** @param AnalyticsData $data */
    public function csv(array $data,bool $revisions=false): string
    {
        $keys=['tripId','bookingId','reference','participantId','person','status','syncStatus','source','versionId','fieldId','label','type','scope','unit','state','value','answerAt'];
        if($revisions){$keys=array_merge($keys,['revisionId','actorId','reason','revisionState','revisionValue','revisionAt','revisionVersionId']);}
        $stream=fopen('php://temp','w+');if($stream===false)throw new \RuntimeException('Cannot open CSV stream');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,array_merge($keys,['stand','filters','population']),';','"','');
        foreach($data[$revisions?'revisionRows':'rows'] as $r){$cells=[];foreach($keys as $key){$value=$r[$key]??null;$numeric=$r['type']==='number'&&in_array($key,['value','revisionValue'],true)&&is_string($value)&&preg_match('/^-?\d+(?:\.\d+)?$/D',$value);$cells[]=$numeric?$value:$this->cell($value);}$cells[]=$data['stand'];$cells[]=$this->cell(json_encode($data['filters'],JSON_UNESCAPED_UNICODE));$cells[]=$data['populationLabel'];fputcsv($stream,$cells,';','"','');}
        rewind($stream);$text=stream_get_contents($stream);fclose($stream);return $text === false ? throw new \RuntimeException('Cannot read CSV stream') : $text;
    }
    private function cell(mixed $value): string
    {
        $s=$value===null?'':(is_bool($value)?($value?'true':'false'):(is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):(string)Input::scalar($value)));
        return preg_match('/^[\s\x00-\x1F]*[=+@-]/u',$s)?"'".$s:$s;
    }

    private static function pageNumber(mixed $v,int $max,string $key): int
    {
        if(!is_scalar($v)||!ctype_digit((string)$v)||(int)$v<1||(int)$v>$max){throw new ValidationException([$key=>'positive_integer_out_of_range']);}
        return (int)$v;
    }
}
