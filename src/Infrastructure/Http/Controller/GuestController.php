<?php
declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\GuestService;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\{ServerRequestInterface as Request,ResponseInterface as Response};
final readonly class GuestController {
 public function __construct(private GuestService $guests) {}
 public function list(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->guests->list((int)$id));}
 public function approve(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->guests->approve((int)$id,(array)$request->getParsedBody()),201);}
 public function revoke(Request $request,Response $response,string $id,string $guestId): Response {return JsonResponder::write($response,$this->guests->revoke((int)$id,(int)$guestId,(array)$request->getParsedBody()));}
}
