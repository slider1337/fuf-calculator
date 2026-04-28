<?php

declare(strict_types=1);

namespace Tests\Integration;

final class ApiIntegrationTest extends ApiTestCase
{
    public function testRootPageContainsDocumentationLinks(): void
    {
        $response = $this->dispatch('GET', '/');

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('/docs', $html);
        self::assertStringContainsString('/openapi.yaml', $html);
    }

    public function testNewTripFrontendRouteIsDirectlyReachable(): void
    {
        $response = $this->dispatch('GET', '/trips/new');

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('trip-editor-section', $html);
        self::assertStringContainsString('Neue Reise erstellen', $html);
    }

    public function testTripFrontendRouteIsDirectlyReachable(): void
    {
        $create = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $create->getStatusCode());
        $created = json_decode((string) $create->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $response = $this->dispatch('GET', '/trips/' . $created['id']);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('trip-editor-section', $html);
        self::assertStringContainsString('result-panel', $html);
    }

    public function testGetSettingsReturnsDefaults(): void
    {
        $response = $this->dispatch('GET', '/api/settings');

        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(10.0, (float) $payload['defaultMarkupPercent']);
        self::assertSame(5.0, (float) $payload['defaultClubFeePercent']);
    }

    public function testCreateTripAndCalculate(): void
    {
        $requestPayload = $this->validTripPayload();

        $createResponse = $this->dispatch('POST', '/api/trips', $requestPayload);
        self::assertSame(201, $createResponse->getStatusCode());

        $created = json_decode((string) $createResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $created);

        $calcResponse = $this->dispatch('POST', '/api/trips/' . $created['id'] . '/calculate');
        self::assertSame(200, $calcResponse->getStatusCode());

        $calculation = json_decode((string) $calcResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PER_PERSON', $calculation['distributionMethod']);
        self::assertArrayHasKey('pricesPerCategory', $calculation);
        self::assertArrayHasKey('ADULT_DOUBLE', $calculation['pricesPerCategory']);

        // Verify breakdowns are included
        self::assertArrayHasKey('priceBreakdowns', $calculation);
        self::assertArrayHasKey('ADULT_DOUBLE', $calculation['priceBreakdowns']);
        $bd = $calculation['priceBreakdowns']['ADULT_DOUBLE'];
        self::assertArrayHasKey('basePricePerPerson', $bd);
        self::assertArrayHasKey('spaTaxPerPerson', $bd);
        self::assertArrayHasKey('groupExpenseShare', $bd);
        self::assertArrayHasKey('subtotalBeforeMarkup', $bd);
        self::assertArrayHasKey('markupAmount', $bd);
        self::assertArrayHasKey('clubFeeAmount', $bd);
        self::assertArrayHasKey('finalPrice', $bd);
        self::assertArrayHasKey('count', $bd);
        self::assertArrayHasKey('categoryRevenue', $bd);
        self::assertSame(100.0, (float) $bd['basePricePerPerson']);
        self::assertSame(3, $bd['count']);
        self::assertArrayHasKey('totalGroupExpenses', $calculation);
    }

    public function testCreateTripWithMissingFieldsReturns422(): void
    {
        $response = $this->dispatch('POST', '/api/trips', ['name' => 'Fehlt']);

        self::assertSame(422, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_error', $payload['error']);
    }

    public function testDocumentationRoutesAreReachable(): void
    {
        $docsResponse = $this->dispatch('GET', '/docs');
        self::assertSame(200, $docsResponse->getStatusCode());
        self::assertStringContainsString('SwaggerUIBundle', (string) $docsResponse->getBody());

        $specResponse = $this->dispatch('GET', '/openapi.yaml');
        self::assertSame(200, $specResponse->getStatusCode());
        self::assertStringContainsString('openapi: 3.0.3', (string) $specResponse->getBody());
    }

    public function testListTripsContainsCreatedTrip(): void
    {
        $create = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $create->getStatusCode());

        $listResponse = $this->dispatch('GET', '/api/trips');
        self::assertSame(200, $listResponse->getStatusCode());

        $list = json_decode((string) $listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($list);
        self::assertArrayHasKey('id', $list[0]);
        self::assertArrayHasKey('name', $list[0]);
        self::assertArrayHasKey('startDate', $list[0]);
    }

    public function testTripListIsSortedByStartDateDescending(): void
    {
        $older = $this->validTripPayload();
        $older['name'] = 'Aeltere Reise';
        $older['startDate'] = '2026-01-10';
        self::assertSame(201, $this->dispatch('POST', '/api/trips', $older)->getStatusCode());

        $newer = $this->validTripPayload();
        $newer['name'] = 'Neuere Reise';
        $newer['startDate'] = '2026-12-20';
        self::assertSame(201, $this->dispatch('POST', '/api/trips', $newer)->getStatusCode());

        $middle = $this->validTripPayload();
        $middle['name'] = 'Mittlere Reise';
        $middle['startDate'] = '2026-06-15';
        self::assertSame(201, $this->dispatch('POST', '/api/trips', $middle)->getStatusCode());

        $listResponse = $this->dispatch('GET', '/api/trips');
        self::assertSame(200, $listResponse->getStatusCode());

        $list = json_decode((string) $listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(3, count($list));
        self::assertSame('2026-12-20', $list[0]['startDate']);
        self::assertSame('2026-06-15', $list[1]['startDate']);
        self::assertSame('2026-01-10', $list[2]['startDate']);
    }

    public function testUpdateTripPersistsChangedValues(): void
    {
        $create = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $create->getStatusCode());
        $created = json_decode((string) $create->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $payload = $this->validTripPayload();
        $payload['name'] = 'API Test Reise Update';
        $payload['distributionMethod'] = 'PER_CATEGORY_UNITS';
        $payload['spaTaxPerPerson'] = 3.5;

        $update = $this->dispatch('PUT', '/api/trips/' . $created['id'], $payload);
        self::assertSame(200, $update->getStatusCode());

        $updated = json_decode((string) $update->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('API Test Reise Update', $updated['name']);
        self::assertSame('PER_CATEGORY_UNITS', $updated['distributionMethod']);
        self::assertSame(3.5, (float) $updated['spaTaxPerPerson']);
    }

    public function testUpdateSettingsSuccessfully(): void
    {
        $payload = [
            'defaultMarkupPercent' => 12.0,
            'defaultClubFeePercent' => 7.5,
            'defaultDistributionMethod' => 'PER_CATEGORY_UNITS',
            'defaultSpaTaxPerPerson' => 3.0,
            'defaultSpaTaxAgeThreshold' => 16,
        ];

        $response = $this->dispatch('PUT', '/api/settings', $payload);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(12.0, (float) $data['defaultMarkupPercent']);
        self::assertSame(7.5, (float) $data['defaultClubFeePercent']);
        self::assertSame('PER_CATEGORY_UNITS', $data['defaultDistributionMethod']);
        self::assertSame(3.0, (float) $data['defaultSpaTaxPerPerson']);
        self::assertSame(16, $data['defaultSpaTaxAgeThreshold']);
    }

    public function testUpdateSettingsPersistsValues(): void
    {
        $payload = [
            'defaultMarkupPercent' => 15.0,
            'defaultClubFeePercent' => 8.0,
            'defaultDistributionMethod' => 'PER_PERSON',
            'defaultSpaTaxPerPerson' => 4.0,
            'defaultSpaTaxAgeThreshold' => 14,
        ];

        $this->dispatch('PUT', '/api/settings', $payload);

        $getResponse = $this->dispatch('GET', '/api/settings');
        $data = json_decode((string) $getResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(15.0, (float) $data['defaultMarkupPercent']);
        self::assertSame(14, $data['defaultSpaTaxAgeThreshold']);
    }

    public function testUpdateSettingsWithMissingFieldsReturns422(): void
    {
        $response = $this->dispatch('PUT', '/api/settings', ['defaultMarkupPercent' => 10.0]);

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_error', $data['error']);
        self::assertArrayHasKey('details', $data);
    }

    public function testGetNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('GET', '/api/trips/99999');

        self::assertSame(404, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('not_found', $data['error']);
    }

    public function testUpdateNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('PUT', '/api/trips/99999', $this->validTripPayload());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCalculateNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('POST', '/api/trips/99999/calculate');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpdateTripWithMissingFieldsReturns422(): void
    {
        $create = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        $created = json_decode((string) $create->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $response = $this->dispatch('PUT', '/api/trips/' . $created['id'], ['name' => 'Unvollstaendig']);

        self::assertSame(422, $response->getStatusCode());
    }
}



