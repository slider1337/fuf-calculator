<?php

declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\{BookingService,AnalyticsService,AccessPolicy,ValidationException};
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\{ResponseInterface as Response,ServerRequestInterface as Request};

final readonly class BookingController
{
    public function __construct(private BookingService $bookings,private AnalyticsService $analytics,private AccessPolicy $policy,private \App\Application\Port\TripRepositoryInterface $trips) {}
    public function list(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->bookings->list((int)$id));}
    public function get(Request $request,Response $response,string $id,string $bookingId): Response {return JsonResponder::write($response,$this->bookings->get((int)$id,(int)$bookingId));}
    public function create(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->bookings->create((int)$id,(array)$request->getParsedBody()),201);}
    public function correct(Request $request,Response $response,string $id,string $bookingId): Response {return JsonResponder::write($response,$this->bookings->correct((int)$id,(int)$bookingId,(array)$request->getParsedBody()));}
    public function cancel(Request $request,Response $response,string $id,string $bookingId): Response {return JsonResponder::write($response,$this->bookings->cancel((int)$id,(int)$bookingId,(array)$request->getParsedBody()));}
    public function room(Request $request,Response $response,string $id,string $bookingId): Response {return JsonResponder::write($response,$this->bookings->assignRoom((int)$id,(int)$bookingId,(array)$request->getParsedBody()));}
    public function analytics(Request $request,Response $response,string $id): Response {return $this->result($request,$response,[(int)$id]);}
    public function eventAnalytics(Request $request,Response $response,string $id): Response
    {
        $eid=(int)$id;$this->policy->require('event',$eid,'event.view');
        $allowed=[];foreach($this->trips->findAll() as $trip){$t=$trip['id'];if(($this->policy->metadata($t)['event_id']??null)===$eid&&$this->policy->can('trip',$t,'analytics.view')&&$this->policy->can('trip',$t,'answers.view')){$allowed[]=$t;}}
        $raw=$request->getQueryParams()['tripIds']??null;
        if($raw===''){$allowed=[];}elseif($raw!==null){if(!is_string($raw)||!preg_match('/^\d+(,\d+)*$/D',$raw)){throw new ValidationException(['tripIds'=>'comma_separated_ids']);}$selected=array_values(array_unique(array_map('intval',explode(',',$raw))));if(array_diff($selected,$allowed)!==[]){throw new \App\Application\AccessException('forbidden');}$allowed=$selected;}
        return $this->result($request,$response,$allowed);
    }
    /** @param list<int> $ids */
    private function result(Request $request,Response $response,array $ids): Response
    {
        $q=$request->getQueryParams();$format=$q['format']??'json';if(!in_array($format,['json','csv','revisions','print'],true)){throw new ValidationException(['format'=>'json_csv_revisions_print']);}
        $data=$this->analytics->read($ids,$q,$format!=='json');
        if(in_array($format,['csv','revisions'],true)){$response->getBody()->write($this->analytics->csv($data,$format==='revisions'));return $response->withHeader('Content-Type','text/csv; charset=utf-8')->withHeader('Content-Disposition','attachment; filename="'.($format==='revisions'?'antwortrevisionen':'antworten').'.csv"')->withHeader('Cache-Control','no-store');}
        if($format==='print'){
            $e=static fn(mixed $v): string=>htmlspecialchars(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):(is_bool($v)?($v?'Ja':'Nein'):(string)\App\Application\Input::scalar($v)),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $html='<!doctype html><html lang="de"><meta charset="utf-8"><title>Auswertung</title><style>body{font-family:system-ui}table{border-collapse:collapse;width:100%}td,th{padding:8px;border:1px solid #ccc;overflow-wrap:anywhere}@media print{button{display:none}}</style><h1>Auswertung</h1><p>'.$e($data['populationLabel']).'</p><p>'.$e($data['totalLabel']).' · '.$e($data['stand']).'</p><p>Filter: '.$e($data['filters']).'</p>';
            foreach($data['metrics'] as $m){$html.='<h2>'.$e($m['field']['label']).'</h2><p>Geprüfte Summe: '.$e($m['sum']??'Liste').' '.$e($m['field']['unit']).' · Ja '.$m['yes'].' / Nein '.$m['no'].' · Grundgesamtheit '.$m['denominator'].'</p><p>'.$e($m['counts']).' · Ungeprüfte Summe: '.$e($m['uncheckedSum']).' · Auswahl: '.$e($m['options']).'</p>';}
            $html.='<table><thead><tr><th>Reise / Buchung</th><th>Person</th><th>Version / Status / Herkunft</th><th>Feld / Einheit</th><th>Zustand / Antwort / Zeit</th></tr></thead><tbody>';
            foreach($data['rows'] as $row){$html.='<tr><td>'.$e($row['tripId'].' / '.$row['reference']).'</td><td>'.$e($row['person']).'</td><td>'.$e($row['versionId'].' / '.$row['status'].' / '.$row['syncStatus'].' / '.$row['source']).'</td><td>'.$e($row['label'].' / '.$row['unit']).'</td><td>'.$e($row['state']).' / '.$e($row['value']).' / '.$e($row['answerAt']).'</td></tr>';}
            $response->getBody()->write($html.'</tbody></table></html>');return $response->withHeader('Content-Type','text/html; charset=utf-8')->withHeader('Cache-Control','no-store');
        }
        return JsonResponder::write($response,$this->analytics->page($data,$q))->withHeader('Cache-Control','no-store');
    }
}
