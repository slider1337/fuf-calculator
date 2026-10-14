<?php

declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use App\Domain\Form\Decimal;

final class BookingAnalyticsApiTest extends ApiTestCase
{
    private function json(string $method,string $path,?array $p=null,int $status=200): array
    {
        $r=$this->dispatch($method,$path,$p);self::assertSame($status,$r->getStatusCode(),"$method $path: ".$r->getBody());
        if($status<300&&(str_contains($path,'/bookings')||str_contains($path,'/analytics'))){
            $spec=\Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');
            $contract=preg_replace('~/(trips|events)/[0-9]+~','/$1/{id}',explode('?',$path)[0]);
            $contract=preg_replace('~/bookings/[0-9]+~','/bookings/{bookingId}',$contract);
            $schema=$spec['paths'][$contract][strtolower($method)]['responses'][(string)$status]['content']['application/json']['schema'];
            self::assertTrue($this->matchesSchema(json_decode((string)$r->getBody(),false,512,JSON_THROW_ON_ERROR),$schema,$spec),"$contract contract");
        }
        return json_decode((string)$r->getBody(),true,512,JSON_THROW_ON_ERROR);
    }
    private function matchesSchema(mixed $v,array $s,array $spec): bool
    {
        if($v===null&&($s['nullable']??false)){return true;}
        if(isset($s['$ref'])){return $this->matchesSchema($v,$spec['components']['schemas'][basename($s['$ref'])],$spec);}
        foreach($s['allOf']??[] as $part){if(!$this->matchesSchema($v,$part,$spec)){return false;}}
        if(isset($s['enum'])&&!in_array($v,$s['enum'],true)){return false;}
        if(!match($s['type']??null){'object'=>$v instanceof \stdClass,'array'=>is_array($v)&&array_is_list($v),'integer'=>is_int($v),'boolean'=>is_bool($v),'string'=>is_string($v),default=>true}){return false;}
        if(($s['type']??null)==='object'){foreach($s['required']??[] as $k){if(!property_exists($v,$k)){return false;}}foreach(get_object_vars($v) as $k=>$value){if(isset($s['properties'][$k])&&!$this->matchesSchema($value,$s['properties'][$k],$spec)){return false;}}}
        if(($s['type']??null)==='array'){foreach($v as $value){if(!$this->matchesSchema($value,$s['items'],$spec)){return false;}}}
        return true;
    }
    private function fixture(array $fields=[]): array
    {
        $e=$this->json('POST','/api/events',['name'=>'Fiktive Tests'],201)['id'];
        $v=$this->json('POST',"/api/events/$e/forms",[],201);
        $v=$this->json('PUT',"/api/events/$e/forms/{$v['id']}",['revision'=>$v['revision'],'fields'=>$fields]);
        $v=$this->json('POST',"/api/events/$e/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Teststand']);
        $t=$this->json('POST','/api/trips',$this->validTripPayload()+['eventId'=>$e],201)['id'];
        $this->json('PUT',"/api/trips/$t/form",['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'reason'=>'Testbindung','requestSupplement'=>false]);return [$e,$t,$v];
    }
    private function payload(array $v,int $people=4): array
    {
        return ['operationKey'=>bin2hex(random_bytes(10)),'contactIdentity'=>'Fiktiver Kontakt','versionId'=>$v['id'],'roomCategory'=>null,'persons'=>array_map(static fn($i)=>['id'=>'p_'.$i,'name'=>'Testperson '.$i,'birthDate'=>$i===0?'1986-01-01':'2018-01-01'],range(0,$people-1)),'answers'=>[]];
    }
    private function confirm(int $id): void
    {
        // Fixture only: there is intentionally no application endpoint for fake external confirmation.
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE registrations SET booking_status='confirmed' WHERE id=$id");$db->exec("UPDATE registration_participants SET participation_status='confirmed' WHERE registration_id=$id");
    }
    public function testFourPeopleStableIdsIdempotencyAndRevisionHistory(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Anzahl','required'=>true],['type'=>'yes_no','label'=>'Entscheidung','scope'=>'person','required'=>true]]);
        $p=$this->payload($v);$f=$v['fields'];$p['answers']=['booking'=>[$f[0]['id']=>'0'],'persons'=>[]];foreach($p['persons'] as $person){$p['answers']['persons'][$person['id']]=[$f[1]['id']=>false];}
        $b=$this->json('POST',"/api/trips/$t/bookings",$p,201);self::assertCount(4,$b['persons']);self::assertSame('pending',$b['status']);self::assertSame('pending',$b['syncStatus']);self::assertNull($b['roomCategory']);self::assertCount(5,$b['revisions']);
        self::assertSame($b,$this->json('POST',"/api/trips/$t/bookings",$p,201));$p['contactIdentity']='Anders';$this->json('POST',"/api/trips/$t/bookings",$p,409);
        $correction=['operationKey'=>'correct_test_1','revision'=>$b['revision'],'reason'=>'Fiktive Korrektur','answers'=>['booking'=>[$f[0]['id']=>'2']]];
        $next=$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correction);self::assertSame(array_column($b['persons'],'id'),array_column($next['persons'],'id'));self::assertCount(6,$next['revisions']);self::assertSame('0',$next['revisions'][0]['value']);self::assertSame('2',$next['answers'][0]['value']);
        self::assertSame($next,$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correction));$correction['operationKey']='correct_test_2';$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correction,409);
        $db=$this->app->getContainer()->get(PDO::class);self::assertSame(1,(int)$db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
        foreach(['UPDATE answer_revisions SET value_json=\'3\'','DELETE FROM answer_revisions'] as $sql){try{$db->exec($sql);self::fail('immutable');}catch(\PDOException $ex){self::assertStringContainsString('immutable_answer',$ex->getMessage());}}
    }
    public function testVersionConflictSupplementAndOldFieldsRemainQueryable(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Alte Menge']]);$p=$this->payload($v,1);$p['answers']=['booking'=>[$v['fields'][0]['id']=>'3']];$b=$this->json('POST',"/api/trips/$t/bookings",$p,201);
        $new=$this->json('POST',"/api/events/$e/forms",['basedOnId'=>$v['id']],201);$new=$this->json('PUT',"/api/events/$e/forms/{$new['id']}",['revision'=>$new['revision'],'fields'=>[['type'=>'yes_no','label'=>'Neu','required'=>true]]]);$new=$this->json('POST',"/api/events/$e/forms/{$new['id']}/publish",['revision'=>$new['revision'],'changeDescription'=>'Neu']);
        $preview=$this->json('POST',"/api/trips/$t/form/preview-switch",['revision'=>1,'versionId'=>$new['id']]);self::assertSame([['versionId'=>$v['id'],'count'=>1]],$preview['existingVersionBreakdown']);
        $this->json('PUT',"/api/trips/$t/form",['revision'=>1,'versionId'=>$new['id'],'targetRevision'=>$new['revision'],'reason'=>'Explizit','requestSupplement'=>true]);
        $p['operationKey']='create_stale_1';$this->json('POST',"/api/trips/$t/bookings",$p,409);
        self::assertTrue($this->json('GET',"/api/trips/$t/bookings/{$b['id']}")['supplementPending']);
        $correct=['operationKey'=>'supplement_test','revision'=>$b['revision'],'versionId'=>$new['id'],'reason'=>'Neuen Stand bestätigt','answers'=>['booking'=>[$new['fields'][0]['id']=>false]]];
        $next=$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correct);self::assertFalse($next['supplementPending']);self::assertSame($new['id'],$next['versionId']);self::assertCount(2,$next['revisions']);
        $data=$this->json('GET',"/api/trips/$t/analytics?status=all&fieldId={$v['fields'][0]['id']}");self::assertSame(1,$data['metrics'][0]['counts']['not_asked']);
    }
    public function testPartialCancellationUncheckedQuantitiesAndClosedTrip(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Menge'],['type'=>'yes_no','label'=>'Person','scope'=>'person']]);$p=$this->payload($v);$p['answers']=['booking'=>[$v['fields'][0]['id']=>'4'],'persons'=>[]];foreach($p['persons'] as $person){$p['answers']['persons'][$person['id']]=[$v['fields'][1]['id']=>true];}
        $b=$this->json('POST',"/api/trips/$t/bookings",$p,201);$this->confirm($b['id']);
        $cancel=['operationKey'=>'partial_cancel_1','revision'=>1,'reason'=>'Eine Person fällt aus','personIds'=>[$b['persons'][0]['id']]];$next=$this->json('POST',"/api/trips/$t/bookings/{$b['id']}/cancel",$cancel);self::assertSame('cancelled',$next['persons'][0]['status']);self::assertSame('unchecked',$next['answers'][0]['state']);self::assertSame($next,$this->json('POST',"/api/trips/$t/bookings/{$b['id']}/cancel",$cancel));
        $d=$this->json('GET',"/api/trips/$t/analytics");self::assertSame('0',$d['metrics'][0]['sum']);self::assertSame('4',$d['metrics'][0]['uncheckedSum']);self::assertSame(3,$d['metrics'][1]['yes']);
        $this->json('POST',"/api/trips/$t/close",['reason'=>'Abgeschlossen']);
        $this->json('POST',"/api/trips/$t/bookings/{$b['id']}/cancel",['operationKey'=>'closed_cancel_1','revision'=>2,'reason'=>'Fällt aus'],409);
        $next=$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",['operationKey'=>'closed_correct_1','revision'=>2,'reason'=>'Geprüfte Menge','answers'=>['booking'=>[$v['fields'][0]['id']=>'3']]]);self::assertSame('answered',$next['answers'][0]['state']);
        $this->json('POST',"/api/trips/$t/reopen",['reason'=>'Test offen']);$next=$this->json('POST',"/api/trips/$t/bookings/{$b['id']}/cancel",['operationKey'=>'cancel_all_test','revision'=>3,'reason'=>'Alles fällt aus']);self::assertSame('cancelled',$next['status']);self::assertSame(0,$this->json('GET',"/api/trips/$t/analytics")['totalRows']);
    }
    public function testTwelveBookingsFortyTwoPeopleAndExportReproduceCounts(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Mahlzeiten','unit'=>'Personen'],['type'=>'checkbox','label'=>'Mit Kindern','visibility'=>['kind'=>'children']],['type'=>'yes_no','label'=>'Wunsch','scope'=>'person'],['type'=>'long_text','label'=>'Text']]);$f=$v['fields'];$personCounter=0;
        for($i=0;$i<12;$i++){
            $p=$this->payload($v,$i<6?4:3); // 6*4 + 6*3 = 42
            if($i>=10){foreach($p['persons'] as &$person){$person['birthDate']='1986-01-01';}unset($person);}
            $p['answers']=['booking'=>[$f[3]['id']=>'=SUM(1,2)'],'persons'=>[]];if($i<11){$p['answers']['booking'][$f[0]['id']]=$i<6?'2':'1';} // 6*2 + 5 = 17
            if($i<9){$p['answers']['booking'][$f[1]['id']]=true;}
            foreach($p['persons'] as $person){if($personCounter<40){$p['answers']['persons'][$person['id']]=[$f[2]['id']=>$personCounter<17];}$personCounter++;}
            $b=$this->json('POST',"/api/trips/$t/bookings",$p,201);$this->confirm($b['id']);
        }
        $d=$this->json('GET',"/api/trips/$t/analytics");$m=$d['metrics'];self::assertSame('17',$m[0]['sum']);self::assertSame(11,$m[0]['counts']['answered']);self::assertSame(1,$m[0]['counts']['missing']);self::assertSame(12,$m[0]['denominator']);self::assertSame(9,$m[1]['yes']);self::assertSame(10,$m[1]['denominator']);self::assertSame(2,$m[1]['counts']['not_applicable']);self::assertSame(17,$m[2]['yes']);self::assertSame(23,$m[2]['no']);self::assertSame(2,$m[2]['counts']['missing']);self::assertSame(42,$m[2]['denominator']);self::assertNull($m[3]['sum']);
        $url="/api/trips/$t/analytics?fieldId={$f[0]['id']}";$detail=$this->json('GET',$url);self::assertSame(12,$detail['totalRows']);self::assertSame($m[0],$detail['metrics'][0]);
        $csv=$this->dispatch('GET',$url.'&format=csv');self::assertSame(200,$csv->getStatusCode());$stream=fopen('php://temp','w+');fwrite($stream,(string)$csv->getBody());rewind($stream);$header=fgetcsv($stream,0,';','"','');$values=[];while($r=fgetcsv($stream,0,';','"','')){$values[]=$r[array_search('value',$header,true)];}self::assertCount(12,$values);self::assertSame('17',Decimal::sum(array_filter($values,static fn($x)=>$x!=='')));fclose($stream);
        $csv=$this->dispatch('GET',"/api/trips/$t/analytics?fieldId={$f[3]['id']}&format=csv");self::assertStringContainsString("'=SUM(1,2)",(string)$csv->getBody());
        self::assertSame(200,$this->dispatch('GET',$url.'&format=print')->getStatusCode());self::assertStringContainsString('Geprüfte Summe: 17',(string)$this->dispatch('GET',$url.'&format=print')->getBody());
        self::assertSame(200,$this->dispatch('GET',$url.'&format=revisions')->getStatusCode());$page=$this->json('GET',$url.'&page=2&pageSize=5');self::assertCount(5,$page['rows']);self::assertSame(12,$page['totalRows']);
    }
    public function testRightsNoAdminBypassEventTotalsAndRevocation(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Zahl']]);$b=$this->json('POST',"/api/trips/$t/bookings",$this->payload($v,1),201);$this->confirm($b['id']);
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'limited@example.com','admin','2026-10-13')");$_SESSION['user_id']=2;
        $this->json('GET',"/api/trips/$t/bookings",null,404);$this->json('GET',"/api/trips/$t/analytics",null,404);
        $_SESSION['user_id']=1;$this->json('PUT',"/api/trips/$t/team/2",['capabilities'=>['trip.view','registration.view'],'reason'=>'Nur Buchungen']);$_SESSION['user_id']=2;
        $limited=$this->json('GET',"/api/trips/$t/bookings/{$b['id']}");self::assertArrayNotHasKey('answers',$limited);self::assertArrayNotHasKey('formVersion',$limited);$this->json('GET',"/api/trips/$t/analytics",null,403);
        $_SESSION['user_id']=1;$this->json('PUT',"/api/events/$e/access/2",['capabilities'=>['event.view'],'reason'=>'Veranstaltung']);$this->json('PUT',"/api/trips/$t/team/2",['capabilities'=>['trip.view','registration.view','answers.view','analytics.view'],'reason'=>'Auswerten']);$_SESSION['user_id']=2;
        $this->json('GET',"/api/trips/$t/analytics");self::assertSame(403,$this->dispatch('GET',"/api/trips/$t/analytics?format=csv")->getStatusCode());$eventData=$this->json('GET',"/api/events/$e/analytics");self::assertSame([$t],array_column($eventData['tripMetrics'],'tripId'));
        $this->json('GET',"/api/events/$e/analytics?tripIds=99999",null,403);
        $_SESSION['user_id']=1;$this->json('PUT',"/api/trips/$t/team/2",['capabilities'=>[],'reason'=>'Widerruf']);$_SESSION['user_id']=2;self::assertSame(0,$this->json('GET',"/api/events/$e/analytics")['totalRows']);$this->json('GET',"/api/trips/$t/bookings/{$b['id']}",null,404);
    }
    public function testPersonCorrectionPreservesIdentityOriginalAndRevalidatesAge(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Menge'],['type'=>'yes_no','label'=>'Kindfrage','scope'=>'person','visibility'=>['kind'=>'child'],'required'=>true]]);
        $p=$this->payload($v,2);$p['answers']=['booking'=>[$v['fields'][0]['id']=>'2'],'persons'=>['p_1'=>[$v['fields'][1]['id']=>false]]];
        $b=$this->json('POST',"/api/trips/$t/bookings",$p,201);
        $people=array_map(static fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'birthDate'=>$p['birthDate']],$b['persons']);$people[0]['birthDate']='2018-01-01';$people[0]['name']='Korrigierte Testperson';
        $correct=['operationKey'=>'person_correction_1','revision'=>1,'reason'=>'Fiktives Geburtsdatum korrigiert','persons'=>$people];
        $this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correct,422);self::assertSame($b,$this->json('GET',"/api/trips/$t/bookings/{$b['id']}"));
        $correct['answers']=['persons'=>[(string)$people[0]['id']=>[$v['fields'][1]['id']=>true]]];
        $next=$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correct);self::assertSame(array_column($b['persons'],'id'),array_column($next['persons'],'id'));self::assertCount(1,$next['personRevisions']);self::assertStringContainsString('1986-01-01',$next['personRevisions'][0]['before_json']);self::assertSame('unchecked',$next['answers'][0]['state']);
        self::assertSame($next,$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correct));
        $this->json('POST',"/api/trips/$t/close",['reason'=>'Abschluss']);$correct['operationKey']='person_correction_2';$correct['revision']=2;$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",$correct,409);
    }
    public function testBookingWithoutAnyFormAndRoomAssignmentAreLocalAndIndependent(): void
    {
        $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];$p=$this->payload(['id'=>null],1);
        $b=$this->json('POST',"/api/trips/$t/bookings",$p,201);self::assertNull($b['formVersion']);self::assertSame([],$b['answers']);
        $b=$this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/room",['operationKey'=>'room_assignment_1','revision'=>1,'reason'=>'Fiktive Zuteilung','roomAssignment'=>'Zimmer 12']);self::assertSame('Zimmer 12',$b['roomAssignment']);self::assertNull($b['roomCategory']);self::assertSame('pending',$b['status']);
        $this->json('PUT',"/api/trips/$t/bookings/{$b['id']}/answers",['operationKey'=>'bad_reason_test','revision'=>2,'reason'=>[]],422);
        $this->json('POST',"/api/trips/$t/bookings",array_replace($p,['operationKey'=>'unknown_answer_test','answers'=>['booking'=>['f_unknown'=>'value']]]),422);
    }
    public function testMalformedInputAndManagedBookingsSurviveLegacyReplacement(): void
    {
        [$e,$t,$v]=$this->fixture([['type'=>'number','label'=>'Zahl','required'=>true]]);$p=$this->payload($v,1);$this->json('POST',"/api/trips/$t/bookings",$p,422);$p['answers']=['booking'=>[$v['fields'][0]['id']=>'0']];
        foreach([['persons'=>false],['persons'=>[['id'=>'x','name'=>'Test','birthDate'=>'2026-02-30']]],['contactIdentity'=>[]],['versionId'=>[]],['answers'=>false]] as $bad){$this->json('POST',"/api/trips/$t/bookings",array_replace($p,$bad),422);}
        $b=$this->json('POST',"/api/trips/$t/bookings",$p,201);$db=$this->app->getContainer()->get(PDO::class);$before=$db->query("SELECT * FROM registrations WHERE id={$b['id']}")->fetch(PDO::FETCH_ASSOC);
        $service=$this->app->getContainer()->get(\App\Application\RegistrationService::class);$csv="Zimmerkategorie;Name Teilnehmer 1;Geburtsdatum Teilnehmer 1\n2-Bettzimmer;CSV Test;01.01.1986\n";$service->importCsv($t,$csv);$service->importCsv($t,$csv);self::assertSame($before,$db->query("SELECT * FROM registrations WHERE id={$b['id']}")->fetch(PDO::FETCH_ASSOC));self::assertSame($b,$this->json('GET',"/api/trips/$t/bookings/{$b['id']}"));
        $this->json('DELETE',"/api/trips/$t/registrations/{$b['id']}",null,409);$this->json('DELETE',"/api/trips/$t/registrations",null,409);
        foreach(['status=bad','page=0','pageSize=999','fieldId=bad','versionId=no'] as $query){$this->json('GET',"/api/trips/$t/analytics?$query",null,422);}
        self::assertSame('1000000000000',Decimal::sum(['999999999999.999999','0.000001']));self::assertSame('-0.3',Decimal::sum(['0.1','-0.4']));
    }
}
