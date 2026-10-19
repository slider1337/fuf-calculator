<?php

declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\{PlanningTransferService,TermService};
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\{ServerRequestInterface as Request,ResponseInterface as Response};

final readonly class WorkflowController
{
    public function __construct(private PlanningTransferService $planning,private TermService $terms,private \App\Application\TermUpdateService $updates,private \App\Application\RegistrationModeService $modes,private \App\Application\TermCatalogService $catalog,private \App\Application\TermWorker $worker,private \App\Infrastructure\TripTermBasisSync $basis,private \App\Application\PriceGroupService $prices) {}
    /** @param array<array-key,mixed> $d */
    private function json(Response $response,array $d,int $status=200): Response {return JsonResponder::write($response,$d,$status)->withHeader('Cache-Control','no-store');}
    public function priceGroups(Request $request,Response $response,string $id):Response {return $this->json($response,$this->prices->read((int)$id));}
    public function savePriceGroup(Request $request,Response $response,string $id,string $groupId):Response {return $this->json($response,$this->prices->save((int)$id,$groupId,(array)$request->getParsedBody()));}
    public function planningStatus(Request $request,Response $response,string $id): Response {return $this->json($response,$this->planning->status((int)$id));}
    public function planningPreview(Request $request,Response $response,string $id): Response {return $this->json($response,$this->planning->preview((int)$id,(array)$request->getParsedBody()));}
    public function planningApply(Request $request,Response $response,string $id): Response {return $this->json($response,$this->planning->apply((int)$id,(array)$request->getParsedBody()));}
    public function createTrip(Request $request,Response $response): Response {return $this->json($response,$this->terms->createTrip((array)$request->getParsedBody()),201);}
    public function registrationMode(Request $request,Response $response,string $id): Response {return $this->json($response,$this->modes->read((int)$id));}
    public function changeRegistrationMode(Request $request,Response $response,string $id): Response {return $this->json($response,$this->modes->change((int)$id,(array)$request->getParsedBody()));}
    public function termUpdates(Request $request,Response $response,string $id): Response {return $this->json($response,$this->updates->read((int)$id));}
    public function termUpdatePreview(Request $request,Response $response,string $id): Response {return $this->json($response,$this->updates->preview((int)$id,(array)$request->getParsedBody()));}
    public function termUpdateSave(Request $request,Response $response,string $id): Response {return $this->json($response,$this->updates->save((int)$id,(array)$request->getParsedBody()));}
    public function termUpdateResolve(Request $request,Response $response,string $id): Response {return $this->json($response,$this->updates->resolve((int)$id,(array)$request->getParsedBody()));}
    public function termUpdateRequest(Request $request,Response $response,string $id): Response {return $this->json($response,$this->updates->request((int)$id,(array)$request->getParsedBody()),202);}
    public function catalogue(Request $request,Response $response,string $resource): Response {return $this->json($response,$this->catalog->search($resource,$request->getQueryParams()));}
    public function tripCatalogue(Request $request,Response $response,string $id,string $resource): Response {return $this->json($response,$this->catalog->search($resource,$request->getQueryParams(),(int)$id));}
    public function term(Request $request,Response $response,string $id): Response {$data=$this->terms->read((int)$id,($request->getQueryParams()['refresh']??null)==='1');$data['basisSync']=$this->basis->status((int)$id);return $this->json($response,$data);}
    public function confirmDeletedTerm(Request $request,Response $response,string $id): Response {return $this->json($response,$this->terms->confirmDeleted((int)$id,(array)$request->getParsedBody()));}
    public function draft(Request $request,Response $response,string $id): Response {return $this->json($response,$this->terms->save((int)$id,(array)$request->getParsedBody()));}
    public function review(Request $request,Response $response,string $id): Response {return $this->json($response,$this->terms->review((int)$id));}
    public function request(Request $request,Response $response,string $id): Response {
        $payload=(array)$request->getParsedBody();
        $result=$this->terms->request((int)$id,$payload);
        if(($payload['executeImmediately']??false)===true&&$result['link']===null){$this->worker->runOne((int)$id);$result=$this->terms->read((int)$id);}
        return $this->json($response,$result,202);
    }
    public function resume(Request $request,Response $response,string $id): Response {
        $payload=(array)$request->getParsedBody();
        $result=$this->terms->resume((int)$id,$payload);
        if(($payload['executeImmediately']??false)===true&&$result['link']===null){$this->worker->runOne((int)$id);$result=$this->terms->read((int)$id);}
        return $this->json($response,$result,202);
    }
    public function link(Request $request,Response $response,string $id): Response {return $this->json($response,$this->terms->link((int)$id,(array)$request->getParsedBody()));}
}
