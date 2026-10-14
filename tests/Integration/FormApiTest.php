<?php

declare(strict_types=1);
namespace Tests\Integration;
use PDO;

final class FormApiTest extends ApiTestCase
{
 protected function validTripPayload(): array {return parent::validTripPayload()+['registrationMode'=>'easyverein'];}

    private function json(string $method,string $path,?array $payload=null,int $status=200): array
    {
        $r=$this->dispatch($method,$path,$payload);self::assertSame($status,$r->getStatusCode(), "$method $path: ".$r->getBody());return json_decode((string)$r->getBody(),true,512,JSON_THROW_ON_ERROR);
    }
    private function event(): int { return $this->json('POST','/api/events',['name'=>'Form-Test'],201)['id']; }
    private function draft(int $e,?int $basedOn=null): array { return $this->json('POST',"/api/events/$e/forms",['basedOnId'=>$basedOn],201); }
    private function field(string $type='number',array $extra=[]): array { return $extra+['type'=>$type,'label'=>'Testfeld']; }
    private function save(int $e,array $v,array $fields,int $status=200): array { return $this->json('PUT',"/api/events/$e/forms/{$v['id']}",['revision'=>$v['revision'],'fields'=>$fields],$status); }
    private function release(int $e,array $v): array { return $this->json('POST',"/api/events/$e/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Fiktive Veröffentlichung']); }
    private function context(): array { return ['startDate'=>'2026-12-20','adultAgeThreshold'=>18,'persons'=>[['id'=>'adult','birthDate'=>'1986-01-01'],['id'=>'child','birthDate'=>'2018-01-01']]]; }
    private function preview(int $e,array $v,array $answers=[],?array $context=null): array { return $this->json('POST',"/api/events/$e/forms/{$v['id']}/preview",['context'=>$context??$this->context(),'answers'=>$answers]); }
    private function trip(int $e): int { return $this->json('POST','/api/trips',$this->validTripPayload()+['eventId'=>$e],201)['id']; }
    private function bind(int $t,array $v,int $revision=0,bool $supplement=false): array { return $this->json('PUT',"/api/trips/$t/form",['revision'=>$revision,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'requestSupplement'=>$supplement,'reason'=>'Bewusster Testwechsel']); }
    public function testStableIdsReloadOrderConflictsAndMeaningChanges(): void
    {
        $e=$this->event();$v=$this->draft($e);$this->draftConflict($e);
        $v=$this->save($e,$v,[$this->field(),$this->field('single_select',['options'=>[['label'=>'A'],['label'=>'B']]])]);
        $id=$v['fields'][0]['id'];$oid=$v['fields'][1]['options'][0]['id'];
        self::assertSame($v,$this->json('GET',"/api/events/$e/forms/{$v['id']}"));
        $old=$v;$fields=array_reverse($v['fields']);$fields[1]['label']='Textkorrektur';
        $v=$this->save($e,$v,$fields);self::assertSame($id,$v['fields'][1]['id']);self::assertSame($oid,$v['fields'][0]['options'][0]['id']);
        $this->save($e,$old,$fields,409);
        $fields=$v['fields'];$fields[1]['unit']='Personen';$v=$this->save($e,$v,$fields);self::assertNotSame($id,$v['fields'][1]['id']);
        $id=$v['fields'][1]['id'];$fields=$v['fields'];$fields[1]['semanticChange']=true;$v=$this->save($e,$v,$fields);self::assertNotSame($id,$v['fields'][1]['id']);
        $fields=$v['fields'];$fields[0]['options'][0]['semanticChange']=true;$v=$this->save($e,$v,$fields);self::assertNotSame($oid,$v['fields'][0]['options'][0]['id']);
        $fields=$v['fields'];$fields[1]['id']='f_'.str_repeat('a',32);$this->save($e,$v,$fields,422);
        $fields=$v['fields'];$fields[0]['options'][0]['id']='o_'.str_repeat('b',32);$this->save($e,$v,$fields,422);
    }
    private function draftConflict(int $e): void { $this->json('POST',"/api/events/$e/forms",[],409); }
    public function testAllAnswerTypesZeroFalseDecimalLimitsAndMissing(): void
    {
        $e=$this->event();$v=$this->draft($e);
        $v=$this->save($e,$v,[
            $this->field('number',['integer'=>false,'required'=>true,'min'=>'0','max'=>'2.5']),
            $this->field('checkbox',['required'=>true]),$this->field('yes_no',['required'=>true]),
            $this->field('single_select',['options'=>[['label'=>'A']],'required'=>true]),
            $this->field('multi_select',['options'=>[['label'=>'A'],['label'=>'B']],'minSelections'=>1,'maxSelections'=>2,'required'=>true]),
            $this->field('short_text',['maxLength'=>3,'required'=>true]),$this->field('long_text',['maxLength'=>5]),
            $this->field('date',['min'=>'2026-01-01','max'=>'2026-12-31','required'=>true]),$this->field('notice'),$this->field('section')]);
        $f=$v['fields'];$valid=['0',true,false,$f[3]['options'][0]['id'],array_column($f[4]['options'],'id'),'abc','long','2026-12-31'];$answers=[];
        foreach($valid as $i=>$value){$answers[$f[$i]['id']]=$value;}
        $r=$this->preview($e,$v,['booking'=>$answers]);self::assertTrue($r['valid']);self::assertSame('0',$r['answers']['booking'][$f[0]['id']]['value']);self::assertFalse($r['answers']['booking'][$f[2]['id']]['value']);self::assertSame('display',$r['answers']['booking'][$f[8]['id']]['state']);
        $invalid=['2.500001',false,'false','invalid-option',[$f[4]['options'][0]['id'],$f[4]['options'][0]['id']],'abcd','too-long','2026-02-30'];
        foreach($invalid as $i=>$bad){$a=$answers;$a[$f[$i]['id']]=$bad;$r=$this->preview($e,$v,['booking'=>$a]);self::assertFalse($r['valid']);self::assertArrayHasKey('booking.'.$f[$i]['id'],$r['errors']);}
        $r=$this->preview($e,$v);self::assertSame('missing',$r['answers']['booking'][$f[0]['id']]['state']);
        $a=$answers;$a[$f[0]['id']]='1.250000';self::assertSame('1.25',$this->preview($e,$v,['booking'=>$a])['answers']['booking'][$f[0]['id']]['value']);
        foreach([0.1,true,'1e2','NaN','0,5','1.1234567'] as $bad){$a=$answers;$a[$f[0]['id']]=$bad;self::assertFalse($this->preview($e,$v,['booking'=>$a])['valid']);}
    }
    public function testScopeVisibilityAgeBoundaryAndNoPreviewWrites(): void
    {
        $e=$this->event();$v=$this->draft($e);$v=$this->save($e,$v,[$this->field('single_select',['options'=>[['label'=>'A'],['label'=>'B']]]),$this->field('yes_no',['scope'=>'person','required'=>true,'visibility'=>['kind'=>'child']]),$this->field('checkbox',['required'=>true,'visibility'=>['kind'=>'children']])]);
        $f=$v['fields'];$fields=$f;$fields[]=$this->field('short_text',['required'=>true,'visibility'=>['kind'=>'selection','fieldId'=>$f[0]['id'],'value'=>$f[0]['options'][0]['id']]]);$v=$this->save($e,$v,$fields);$f=$v['fields'];
        $r=$this->preview($e,$v,['booking'=>[$f[0]['id']=>$f[0]['options'][1]['id'],$f[2]['id']=>true],'persons'=>['child'=>[$f[1]['id']=>false]]]);
        self::assertTrue($r['valid']);self::assertSame('not_applicable',$r['answers']['persons']['adult'][$f[1]['id']]['state']);self::assertSame('not_applicable',$r['answers']['booking'][$f[3]['id']]['state']);
        $context=$this->context();$context['persons']=[['id'=>'boundary','birthDate'=>'2008-12-20']];$r=$this->preview($e,$v,[],$context);self::assertTrue($r['valid']);self::assertFalse($r['persons'][0]['child']);self::assertSame('not_applicable',$r['answers']['booking'][$f[2]['id']]['state']);
        $r=$this->preview($e,$v,['booking'=>[$f[1]['id']=>true]]);self::assertArrayHasKey('booking.'.$f[1]['id'],$r['errors']);
        $db=$this->app->getContainer()->get(PDO::class);self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM form_supplement_requests')->fetchColumn());
    }
    public function testSchemaRejectsInvalidRulesAndUnsafeMapping(): void
    {
        $e=$this->event();$v=$this->draft($e);
        foreach([
            [$this->field('unknown')],[$this->field('number',['min'=>'5','max'=>'4'])],[$this->field('number',['min'=>[]])],
            [$this->field('date',['min'=>'2026-02-30'])],[$this->field('multi_select',['options'=>[['label'=>'A']],'maxSelections'=>2])],
            [$this->field('yes_no',['visibility'=>['kind'=>'child']])],[$this->field('checkbox',['visibility'=>['kind'=>'selection','fieldId'=>'missing','value'=>true]])],
            [$this->field('number',['mapping'=>'eval(1+1)'])],[$this->field('short_text',['mapping'=>'vegetarianMeals'])],[$this->field('short_text',['maxLength'=>0])]
        ] as $fields){$this->save($e,$v,$fields,422);}
        $v=$this->save($e,$v,[$this->field('yes_no'),$this->field('yes_no')]);$fields=$v['fields'];$fields[0]['visibility']=['kind'=>'selection','fieldId'=>$fields[1]['id'],'value'=>true];$fields[1]['visibility']=['kind'=>'selection','fieldId'=>$fields[0]['id'],'value'=>true];$this->save($e,$v,$fields,422);
    }
    public function testPublicationIndependentBindingsArchivingAndSwitchConcurrency(): void
    {
        $e=$this->event();$v=$this->save($e,$this->draft($e),[$this->field()]);$release=$this->release($e,$v);
        self::assertSame(1,$release['number']);self::assertNotNull($release['publishedAt']);self::assertSame(1,$release['publishedBy']);
        $this->save($e,$release,$release['fields'],409);$this->json('POST',"/api/events/$e/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Stale'],409);
        $a=$this->trip($e);$b=$this->trip($e);$this->bind($a,$release);$this->bind($b,$release);
        $draft=$this->draft($e,$release['id']);$fields=$draft['fields'];$fields[0]['label']='Korrigiert';$fields[]=$this->field('yes_no',['scope'=>'person']);$draft=$this->save($e,$draft,$fields);$next=$this->release($e,$draft);
        self::assertSame($release['id'],$this->json('GET',"/api/trips/$a/form")['versionId']);self::assertSame($release['id'],$this->json('GET',"/api/trips/$b/form")['versionId']);
        $diff=$this->json('GET',"/api/events/$e/forms/compare?from={$release['id']}&to={$next['id']}");self::assertSame(['changed','added'],array_column($diff['rows'],'change'));self::assertTrue($diff['rows'][0]['compatibleSeries']);
        $preview=$this->json('POST',"/api/trips/$a/form/preview-switch",['revision'=>1,'versionId'=>$next['id']]);self::assertSame(0,$preview['existingRegistrationCount']);self::assertTrue($preview['existingVersionBreakdownAvailable']);self::assertSame([],$preview['existingVersionBreakdown']);
        $changed=$this->bind($a,$next,1,true);self::assertSame(2,$changed['revision']);self::assertCount(2,$changed['history']);
        self::assertSame($release['id'],$this->json('GET',"/api/trips/$b/form")['versionId']);
        $this->json('PUT',"/api/trips/$a/form",['revision'=>1,'versionId'=>$release['id'],'targetRevision'=>$release['revision'],'requestSupplement'=>false,'reason'=>'Stale'],409);
        $archived=$this->json('POST',"/api/events/$e/forms/{$release['id']}/archive",['revision'=>$release['revision'],'reason'=>'Historisch']);self::assertSame('archived',$archived['status']);
        self::assertSame($release['fields'],$this->json('GET',"/api/trips/$b/form")['version']['fields']);self::assertSame([$next['id']],array_column($this->json('GET',"/api/trips/$b/form")['availableVersions'],'id'));
        $this->json('PUT',"/api/trips/$a/event",['eventId'=>null,'reason'=>'Entfernen'],409);
        $other=$this->event();$foreign=$this->release($other,$this->draft($other));$this->json('POST',"/api/trips/$b/form/preview-switch",['revision'=>1,'versionId'=>$foreign['id']],422);
        $this->json('POST',"/api/trips/$b/close",['reason'=>'Testabschluss']);$this->json('POST',"/api/trips/$b/form/preview-switch",['revision'=>1,'versionId'=>$next['id']],409);
    }
    public function testSupplementRequestsPreserveLegacyDataAndAreDurable(): void
    {
        $e=$this->event();$t=$this->trip($e);$db=$this->app->getContainer()->get(PDO::class);
        $db->exec("INSERT INTO registrations (trip_id,room_category,comment,source,billing_total) VALUES ($t,'DZ','Test','csv',12.34)");$rid=(int)$db->lastInsertId();$db->exec("INSERT INTO registration_participants (registration_id,name,birth_date) VALUES ($rid,'Fiktive Person','1986-01-01')");$before=$db->query("SELECT * FROM registrations WHERE id=$rid")->fetch(PDO::FETCH_ASSOC);
        $v=$this->release($e,$this->draft($e));$this->bind($t,$v,0,true);
        self::assertSame($before,$db->query("SELECT * FROM registrations WHERE id=$rid")->fetch(PDO::FETCH_ASSOC));
        $jobs=$db->query('SELECT * FROM form_supplement_requests')->fetchAll(PDO::FETCH_ASSOC);self::assertCount(1,$jobs);self::assertSame($rid,$jobs[0]['registration_id']);self::assertSame('pending',$jobs[0]['status']);
        $this->json('DELETE',"/api/trips/$t/registrations/$rid");$job=$db->query('SELECT * FROM form_supplement_requests')->fetch(PDO::FETCH_ASSOC);self::assertNull($job['registration_id']);self::assertSame($rid,$job['registration_reference']);self::assertSame('obsolete',$job['status']);
    }
    public function testRightsRevocationTripOnlyDefinitionsAndNoAdminBypass(): void
    {
        $e=$this->event();$t=$this->trip($e);$v=$this->release($e,$this->draft($e));$this->bind($t,$v);$this->draft($e,$v['id']);
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'other@example.com','admin','2026-10-13')");
        $_SESSION['user_id']=2;$_SESSION['user_role']='admin';$this->json('GET',"/api/events/$e/forms",null,403);$this->json('GET',"/api/trips/$t/form",null,404);
        $_SESSION['user_id']=1;$this->json('PUT',"/api/trips/$t/team/2",['capabilities'=>['trip.view'],'reason'=>'Reise lesen']);
        $_SESSION['user_id']=2;$binding=$this->json('GET',"/api/trips/$t/form");self::assertSame($v['fields'],$binding['version']['fields']);self::assertSame([],$binding['availableVersions']);$this->json('GET',"/api/events/$e/forms",null,403);
        $_SESSION['user_id']=1;$this->json('PUT',"/api/events/$e/access/2",['capabilities'=>['event.view','form.manage'],'reason'=>'Formular bearbeiten']);
        $_SESSION['user_id']=2;$draft=$this->json('GET',"/api/events/$e/forms")[0];$this->save($e,$draft,[]);$this->json('POST',"/api/events/$e/forms/{$draft['id']}/publish",['revision'=>2,'changeDescription'=>'Unberechtigt'],403);
        $_SESSION['user_id']=1;$this->json('PUT',"/api/events/$e/access/2",['capabilities'=>[],'reason'=>'Widerruf']);$_SESSION['user_id']=2;$this->json('GET',"/api/events/$e/forms",null,403);
    }
    public function testDatabaseProtectsReleasedDefinitions(): void
    {
        $e=$this->event();$v=$this->release($e,$this->save($e,$this->draft($e),[$this->field()]));$db=$this->app->getContainer()->get(PDO::class);
        foreach(["UPDATE form_fields SET definition='{}' WHERE version_id={$v['id']}","DELETE FROM form_fields WHERE version_id={$v['id']}","UPDATE form_versions SET status='draft' WHERE id={$v['id']}","UPDATE form_versions SET change_description='Overwrite' WHERE id={$v['id']}"] as $sql){try{$db->exec($sql);self::fail('Release must be immutable');}catch(\PDOException $ex){self::assertStringContainsString('immutable_form',$ex->getMessage());}}
        self::assertSame($v,$this->json('GET',"/api/events/$e/forms/{$v['id']}"));
    }
    public function testMalformedInputsNeverBecomeServerErrors(): void
    {
        $e=$this->event();$v=$this->draft($e);
        foreach ([['type'=>[],'label'=>'X'],['type'=>'number'],['type'=>'number','label'=>[]],['type'=>'number','label'=>'X','scope'=>[]],['type'=>'number','label'=>'X','visibility'=>['kind'=>'selection','fieldId'=>[]]],['type'=>'short_text','label'=>'X','maxLength'=>[]],['type'=>'multi_select','label'=>'X','options'=>[],'minSelections'=>[]],['type'=>'checkbox','label'=>'X','min'=>[]],['type'=>'number','label'=>'X','mapping'=>[]]] as $field) {$this->save($e,$v,[$field],422);}
        $v=$this->save($e,$v,[$this->field('number'),$this->field('yes_no',['scope'=>'person'])]);
        foreach ([['context'=>[]],['context'=>$this->context(),'answers'=>['persons'=>['child'=>'invalid']]],['context'=>$this->context(),'answers'=>['persons'=>['foreign'=>[]]]],['context'=>$this->context(),'answers'=>['booking'=>false]]] as $p) {$this->json('POST',"/api/events/$e/forms/{$v['id']}/preview",$p,422);}
        $fields=$v['fields'];$fields[]=$fields[0];$this->save($e,$v,$fields,422);
    }
    public function testTripPreviewUsesItsActualStartAndAgeThreshold(): void
    {
        $e=$this->event();$t=$this->trip($e);$v=$this->save($e,$this->draft($e),[$this->field('yes_no',['scope'=>'person','visibility'=>['kind'=>'child'],'required'=>true])]);
        $r=$this->json('POST',"/api/events/$e/forms/{$v['id']}/preview",['tripId'=>$t,'context'=>['persons'=>[['id'=>'edge','birthDate'=>'2010-12-20']]]]);
        self::assertTrue($r['valid']);self::assertSame('2026-12-20',$r['startDate']);self::assertSame(16,$r['adultAgeThreshold']);self::assertFalse($r['persons'][0]['child']);
        $other=$this->event();$this->json('POST',"/api/events/$other/forms/{$v['id']}/preview",['tripId'=>$t,'context'=>$this->context()],404);
        $this->json('PUT',"/api/events/$e",['name'=>'Archiv','archived'=>true,'revision'=>1]);$this->save($e,$v,$v['fields'],409);$this->json('POST',"/api/events/$e/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Archiv'],409);
    }

}
