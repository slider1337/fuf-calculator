<?php
declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\ParticipationService;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\{ResponseInterface as Response,ServerRequestInterface as Request};
final readonly class ParticipationController {
 public function __construct(private ParticipationService $sync) {}
 public function read(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->sync->read((int)$id))->withHeader('Cache-Control','no-store');}
 public function request(Request $request,Response $response,string $id,string $bookingId): Response {return JsonResponder::write($response,$this->sync->request((int)$id,(int)$bookingId,(array)$request->getParsedBody()),202);}
 public function retry(Request $request,Response $response,string $id,string $jobId): Response {return JsonResponder::write($response,$this->sync->retry((int)$id,(int)$jobId),202);}
 public function reconcile(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->sync->reconcile((int)$id));}
 public function assign(Request $request,Response $response,string $id,string $externalId): Response {return JsonResponder::write($response,$this->sync->assignExternal((int)$id,$externalId,(array)$request->getParsedBody()));}
 public function resolvePerson(Request $request,Response $response,string $id,string $personId): Response {return JsonResponder::write($response,$this->sync->resolvePerson((int)$id,(int)$personId,(array)$request->getParsedBody()));}
 public function acknowledge(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->sync->acknowledge((int)$id,(array)$request->getParsedBody()));}
}
