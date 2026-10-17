<?php

declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\AccessPolicy;
use App\Application\EventService;
use App\Application\TripManagementService;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class EventController
{
    public function __construct(private EventService $events, private AccessPolicy $policy, private TripManagementService $management, private \App\Application\Port\UserRepositoryInterface $users) {}
    public function list(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        return JsonResponder::write($response, $this->events->list((string) \App\Application\Input::scalar(($q['q'] ?? '')), ($q['archived'] ?? '') === 'true'));
    }
    public function create(Request $request, Response $response): Response { return JsonResponder::write($response, $this->events->create((array) $request->getParsedBody()), 201); }
    public function get(Request $request, Response $response, string $id): Response { return JsonResponder::write($response, $this->events->get((int) $id)); }
    public function update(Request $request, Response $response, string $id): Response { return JsonResponder::write($response, $this->events->update((int) $id, (array) $request->getParsedBody())); }
    public function assign(Request $request, Response $response, string $id): Response
    {
        $p = (array) $request->getParsedBody();
        $this->events->assign((int) $id, isset($p['eventId']) ? (int) \App\Application\Input::scalar($p['eventId']) : null, (string) \App\Application\Input::scalar(($p['reason'] ?? '')));
        return JsonResponder::write($response, ['ok' => true]);
    }
    private function target(Request $request): string
    {
        $path = $request->getUri()->getPath();
        return str_contains($path, '/default-team') ? 'default' : (str_contains($path, '/events/') ? 'event' : 'trip');
    }
    public function team(Request $request, Response $response, string $id): Response { return JsonResponder::write($response, $this->policy->team($this->target($request), (int) $id)); }
    public function grant(Request $request, Response $response, string $id, string $userId): Response
    {
        $p = (array) $request->getParsedBody();
        $caps = $p['capabilities'] ?? null;
        if (!is_array($caps) || count(array_filter($caps, 'is_string')) !== count($caps)) { throw new \App\Application\ValidationException(['capabilities' => 'array_of_strings_required']); }
        $this->policy->grant($this->target($request), (int) $id, (int) $userId, \App\Application\Input::strings($caps), (string) \App\Application\Input::scalar(($p['reason'] ?? '')));
        return JsonResponder::write($response, ['ok' => true]);
    }
    public function close(Request $request, Response $response, string $id): Response
    {
        $p = (array) $request->getParsedBody(); $this->management->close((int) $id, (string) \App\Application\Input::scalar(($p['reason'] ?? '')));
        return JsonResponder::write($response, ['ok' => true]);
    }
    public function reopen(Request $request, Response $response, string $id): Response
    {
        $p = (array) $request->getParsedBody(); $this->management->reopen((int) $id, (string) \App\Application\Input::scalar(($p['reason'] ?? '')));
        return JsonResponder::write($response, ['ok' => true]);
    }
    public function removeTrip(Request $request,Response $response,string $id): Response {return JsonResponder::write($response,$this->management->remove((int)$id,(array)$request->getParsedBody()));}
    public function archive(Request $request, Response $response, string $id): Response { return JsonResponder::write($response,$this->management->archive((int)$id,(array)$request->getParsedBody())); }
    public function duplicate(Request $request, Response $response, string $id): Response { return JsonResponder::write($response, $this->management->duplicate((int) $id, (array) $request->getParsedBody()), 201); }
    public function legacyPreview(Request $request, Response $response): Response { return JsonResponder::write($response, $this->policy->candidates()); }
    public function legacyImport(Request $request, Response $response): Response
    {
        $p = (array) $request->getParsedBody();
        $this->management->legacyImport((array) ($p['pairs'] ?? []), (string) \App\Application\Input::scalar(($p['reason'] ?? '')));
        return JsonResponder::write($response, ['ok' => true]);
    }
    public function people(Request $request, Response $response): Response
    {
        $id = (int) (\Slim\Routing\RouteContext::fromRequest($request)->getRoute()?->getArgument('id') ?? 0);
        $type = str_contains($request->getUri()->getPath(), '/events/') ? 'event' : 'trip';
        $this->policy->team($type, $id);
        return JsonResponder::write($response, array_map(static fn ($user) => ['id' => $user->id(), 'email' => $user->email()], $this->users->findAll()));
    }
}
