<?php

declare(strict_types=1);
namespace Tests\Integration;
use Symfony\Component\Yaml\Yaml;

final class FormOpenApiContractTest extends ApiTestCase
{
    private array $spec;
    private function response(string $method,string $path,string $contract,?array $body=null,int $status=200): array
    {
        $r=$this->dispatch($method,$path,$body);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());
        $data=json_decode((string)$r->getBody(),false,512,JSON_THROW_ON_ERROR);
        $schema=$this->spec['paths'][$contract][strtolower($method)]['responses'][(string)$status]['content']['application/json']['schema'];
        self::assertTrue($this->matchesContract($data,$schema),"$method $path response schema");return json_decode((string)$r->getBody(),true,512,JSON_THROW_ON_ERROR);
    }
    public function testAllFormOperationsReturnTheirDocumentedStructures(): void
    {
        $this->spec=Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');
        $e=json_decode((string)$this->dispatch('POST','/api/events',['name'=>'Vertrag'])->getBody(),true)['id'];
        $prefix="/api/events/$e/forms";$contract='/api/events/{eventId}/forms';
        $v=$this->response('POST',$prefix,$contract,[],201);
        $this->response('GET',$prefix,$contract);
        $v=$this->response('PUT',"$prefix/{$v['id']}","$contract/{versionId}",['revision'=>$v['revision'],'fields'=>[['type'=>'number','label'=>'Anzahl','min'=>'0','max'=>'10','required'=>true],['type'=>'yes_no','label'=>'Entscheidung','scope'=>'person','required'=>true]]]);
        $this->response('GET',"$prefix/{$v['id']}","$contract/{versionId}");
        $context=['startDate'=>'2026-12-20','adultAgeThreshold'=>18,'persons'=>[['id'=>'a','birthDate'=>'1986-01-01']]];
        $r=$this->response('POST',"$prefix/{$v['id']}/preview","$contract/{versionId}/preview",['context'=>$context,'answers'=>['booking'=>[$v['fields'][0]['id']=>'0'],'persons'=>['a'=>[$v['fields'][1]['id']=>false]]]]);self::assertTrue($r['valid']);
        $this->response('POST',"$prefix/{$v['id']}/preview","$contract/{versionId}/preview",['context'=>$context]);
        $v=$this->response('POST',"$prefix/{$v['id']}/publish","$contract/{versionId}/publish",['revision'=>$v['revision'],'changeDescription'=>'Erste Fassung']);
        $draft=$this->response('POST',$prefix,$contract,['basedOnId'=>$v['id']],201);
        $this->response('GET',"$prefix/compare?from={$v['id']}&to={$draft['id']}","$contract/compare");
        $trip=json_decode((string)$this->dispatch('POST','/api/trips',$this->validTripPayload()+['eventId'=>$e])->getBody(),true)['id'];
        $this->response('GET',"/api/trips/$trip/form",'/api/trips/{id}/form');
        $this->response('POST',"/api/trips/$trip/form/preview-switch",'/api/trips/{id}/form/preview-switch',['revision'=>0,'versionId'=>$v['id']]);
        $this->response('PUT',"/api/trips/$trip/form",'/api/trips/{id}/form',['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'requestSupplement'=>false,'reason'=>'Bewusste Bindung']);
        $this->response('GET',"/api/trips/$trip/form",'/api/trips/{id}/form');
        $this->response('POST',"$prefix/{$v['id']}/archive","$contract/{versionId}/archive",['revision'=>$v['revision'],'reason'=>'Archiviert']);
        $this->response('GET',"/api/trips/$trip/form",'/api/trips/{id}/form');
    }
    private function matchesContract(mixed $v,array $s): bool
    {
        if($v===null && ($s['nullable']??false)){return true;}
        if(isset($s['$ref'])){return $this->matchesContract($v,$this->spec['components']['schemas'][basename($s['$ref'])]);}
        foreach($s['allOf']??[] as $part){if(!$this->matchesContract($v,$part)){return false;}}
        if(isset($s['oneOf'])){return count(array_filter($s['oneOf'],fn($part)=>$this->matchesContract($v,$part)))===1;}
        if(isset($s['enum'])&&!in_array($v,$s['enum'],true)){return false;}
        $valid=match($s['type']??null){'object'=>$v instanceof \stdClass,'array'=>is_array($v)&&array_is_list($v),'integer'=>is_int($v),'boolean'=>is_bool($v),'string'=>is_string($v),default=>true};
        if(!$valid){return false;}
        if(($s['type']??null)==='object'){
            foreach($s['required']??[] as $key){if(!property_exists($v,$key)){return false;}}
            foreach(get_object_vars($v) as $key=>$value){$sub=$s['properties'][$key]??($s['additionalProperties']??null);if(is_array($sub)&&!$this->matchesContract($value,$sub)){return false;}}
        }
        if(($s['type']??null)==='array'){foreach($v as $value){if(!$this->matchesContract($value,$s['items'])){return false;}}}
        return true;
    }
}
