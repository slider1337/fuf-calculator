<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;
use Symfony\Component\Yaml\Yaml;

final class OpenApiContractTest extends ApiTestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spec = Yaml::parseFile(__DIR__ . '/../../docs/openapi.yaml');
    }

    /**
     * @throws JsonException
     */
    public function testSettingsResponseContainsAllRequiredSchemaFields(): void
    {
        $this->assertPathAndStatusDefined('/api/settings', 'get', '200');

        $response = $this->dispatch('GET', '/api/settings');
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($this->requiredFieldsForSchema('Settings') as $requiredField) {
            self::assertArrayHasKey($requiredField, $payload);
        }
    }

    /**
     * @throws JsonException
     */
    public function testCalculateResponseContainsAllExpectedFieldsFromSchema(): void
    {
        $create = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $create->getStatusCode());

        $created = json_decode((string) $create->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertPathAndStatusDefined('/api/trips/{id}/calculate', 'post', '200');

        $response = $this->dispatch('POST', '/api/trips/' . $created['id'] . '/calculate');
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($this->schemaProperties('CalculationResult') as $property) {
            self::assertArrayHasKey($property, $payload);
        }
    }

    private function assertPathAndStatusDefined(string $path, string $method, string $statusCode): void
    {
        self::assertArrayHasKey($path, $this->spec['paths']);
        self::assertArrayHasKey($method, $this->spec['paths'][$path]);
        self::assertArrayHasKey($statusCode, $this->spec['paths'][$path][$method]['responses']);
    }

    private function requiredFieldsForSchema(string $schemaName): array
    {
        $schema = $this->spec['components']['schemas'][$schemaName] ?? [];

        return $schema['required'] ?? [];
    }

    private function schemaProperties(string $schemaName): array
    {
        $schema = $this->spec['components']['schemas'][$schemaName] ?? [];

        return array_keys($schema['properties'] ?? []);
    }
}

