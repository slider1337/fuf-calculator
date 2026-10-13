<?php

declare(strict_types=1);

namespace Tests\Integration;

use Symfony\Component\Yaml\Yaml;

final class EventOpenApiContractTest extends ApiTestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spec = Yaml::parseFile(__DIR__ . '/../../docs/openapi.yaml');
    }

    public function testEveryRegisteredApiOperationHasADocumentedContract(): void
    {
        foreach ($this->app->getRouteCollector()->getRoutes() as $route) {
            if (!str_starts_with($route->getPattern(), '/api/')) { continue; }
            $path = preg_replace('/\{(\w+):[^}]+\}/', '{$1}', $route->getPattern());
            foreach ($route->getMethods() as $method) {
                self::assertArrayHasKey(strtolower($method), $this->spec['paths'][$path] ?? [], "$method $path");
            }
        }
    }

    public function testRealEventTeamAndLifecycleResponsesMatchTheirSchemas(): void
    {
        $event = $this->response('POST', '/api/events', '/api/events', ['name' => 'Vertragstest'], 201);
        $e = $event['id'];
        $trip = $this->response('POST', '/api/trips', '/api/trips', $this->validTripPayload(), 201);
        $t = $trip['id'];
        $this->response('PUT', "/api/trips/$t/event", '/api/trips/{id}/event', ['eventId' => $e, 'reason' => 'Testzuordnung']);
        $this->response('GET', '/api/events', '/api/events');
        $this->response('GET', "/api/events/$e", '/api/events/{id}');
        $this->response('PUT', "/api/events/$e", '/api/events/{id}', ['name' => 'Neuer Name', 'revision' => 1]);
        foreach (["/api/events/$e/access" => '/api/events/{id}/access', "/api/events/$e/default-team" => '/api/events/{id}/default-team', "/api/trips/$t/team" => '/api/trips/{id}/team'] as $path => $contract) {
            $this->response('PUT', "$path/1", "$contract/{userId}", ['capabilities' => str_contains($path, '/access') ? ['event.view', 'event.manage', 'access.manage'] : \App\Application\AccessPolicy::TRIP_CAPABILITIES, 'reason' => 'Vertragstest']);
            $this->response('GET', $path, $contract);
        }
        $this->response('GET', "/api/events/$e/people", '/api/events/{id}/people');
        $this->response('GET', "/api/trips/$t/people", '/api/trips/{id}/people');
        $this->response('GET', "/api/trips/$t/settlement", '/api/trips/{id}/settlement');
        $this->response('POST', "/api/trips/$t/close", '/api/trips/{id}/close', ['reason' => 'Abschluss']);
        $this->response('GET', "/api/trips/$t", '/api/trips/{id}');
        $this->response('GET', '/api/trips', '/api/trips');
        $this->response('GET', "/api/trips/$t/settlement", '/api/trips/{id}/settlement');
        $this->response('POST', "/api/trips/$t/reopen", '/api/trips/{id}/reopen', ['reason' => 'Korrektur']);
        $this->response('POST', "/api/trips/$t/duplicate", '/api/trips/{id}/duplicate', ['name' => 'Kopie', 'startDate' => '2028-01-01'], 201);
        $this->response('GET', '/api/admin/legacy-access', '/api/admin/legacy-access');
    }

    private function response(string $method, string $url, string $contract, ?array $body = null, int $status = 200): array
    {
        $response = $this->dispatch($method, $url, $body);
        self::assertSame($status, $response->getStatusCode(), "$method $url: " . $response->getBody());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $schema = $this->spec['paths'][$contract][strtolower($method)]['responses'][(string) $status]['content']['application/json']['schema'];
        self::assertTrue($this->matchesSchema($payload, $schema), "$method $url does not match its response schema");
        return $payload;
    }

    // Checks the response structures used here: references, composition, required fields,
    // nested scalar types, arrays and enums. Not a general-purpose OpenAPI validator.
    private function matchesSchema(mixed $value, array $schema): bool
    {
        if ($value === null && ($schema['nullable'] ?? false)) { return true; }
        if (isset($schema['$ref'])) {
            $name = basename($schema['$ref']);
            return $this->matchesSchema($value, $this->spec['components']['schemas'][$name]);
        }
        foreach ($schema['allOf'] ?? [] as $part) {
            if (!$this->matchesSchema($value, $part)) { return false; }
        }
        if (isset($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $part) {
                if ($this->matchesSchema($value, $part)) { return true; }
            }
            return false;
        }
        $validType = match ($schema['type'] ?? null) {
            'object' => is_array($value),
            'array' => is_array($value) && array_is_list($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'string' => is_string($value),
            default => true,
        };
        if (!$validType || (isset($schema['enum']) && !in_array($value, $schema['enum'], true))) { return false; }
        if (($schema['type'] ?? null) === 'object') {
            foreach ($schema['required'] ?? [] as $key) {
                if (!array_key_exists($key, $value)) { return false; }
            }
            foreach ($schema['properties'] ?? [] as $key => $property) {
                if (array_key_exists($key, $value) && !$this->matchesSchema($value[$key], $property)) { return false; }
            }
        }
        if (($schema['type'] ?? null) === 'array') {
            foreach ($value as $item) {
                if (!$this->matchesSchema($item, $schema['items'])) { return false; }
            }
        }
        return true;
    }
}
