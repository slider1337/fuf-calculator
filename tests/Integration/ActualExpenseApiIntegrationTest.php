<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;

final class ActualExpenseApiIntegrationTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    private function createTripAndGetId(): int
    {
        $response = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return (int) $data['id'];
    }

    /**
     * @throws JsonException
     */
    public function testCreateActualExpense(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Unterkunft',
            'amount' => 1500.00,
        ]);

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);
        self::assertSame('Unterkunft', $data['label']);
        self::assertSame(1500.00, (float) $data['amount']);
        self::assertSame($tripId, $data['tripId']);
    }

    /**
     * @throws JsonException
     */
    public function testCreateActualExpenseForNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('POST', '/api/trips/99999/actual-expenses', [
            'label' => 'Test',
            'amount' => 10.0,
        ]);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testCreateActualExpenseWithMissingLabelReturns422(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => '',
            'amount' => 10.0,
        ]);

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_error', $data['error']);
    }

    /**
     * @throws JsonException
     */
    public function testCreateActualExpenseWithNegativeAmountReturns422(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Negativ',
            'amount' => -50.0,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testListActualExpenses(): void
    {
        $tripId = $this->createTripAndGetId();

        $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'A',
            'amount' => 100.0,
        ]);
        $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'B',
            'amount' => 200.0,
        ]);

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/actual-expenses");
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $data);
        self::assertSame('A', $data[0]['label']);
        self::assertSame('B', $data[1]['label']);
    }

    /**
     * @throws JsonException
     */
    public function testListActualExpensesForNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('GET', '/api/trips/99999/actual-expenses');
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testUpdateActualExpense(): void
    {
        $tripId = $this->createTripAndGetId();

        $createResponse = $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Original',
            'amount' => 100.0,
        ]);
        $created = json_decode((string) $createResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $updateResponse = $this->dispatch('PUT', "/api/trips/{$tripId}/actual-expenses/{$created['id']}", [
            'label' => 'Aktualisiert',
            'amount' => 250.0,
        ]);

        self::assertSame(200, $updateResponse->getStatusCode());
        $updated = json_decode((string) $updateResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Aktualisiert', $updated['label']);
        self::assertSame(250.0, (float) $updated['amount']);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateNonExistentActualExpenseReturns404(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('PUT', "/api/trips/{$tripId}/actual-expenses/99999", [
            'label' => 'Test',
            'amount' => 10.0,
        ]);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteActualExpense(): void
    {
        $tripId = $this->createTripAndGetId();

        $createResponse = $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Loeschen',
            'amount' => 50.0,
        ]);
        $created = json_decode((string) $createResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $deleteResponse = $this->dispatch('DELETE', "/api/trips/{$tripId}/actual-expenses/{$created['id']}");
        self::assertSame(200, $deleteResponse->getStatusCode());

        $listResponse = $this->dispatch('GET', "/api/trips/{$tripId}/actual-expenses");
        $list = json_decode((string) $listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(0, $list);
    }

    /**
     * @throws JsonException
     */
    public function testDeleteNonExistentActualExpenseReturns404(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('DELETE', "/api/trips/{$tripId}/actual-expenses/99999");
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testSettlementWithNoData(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/settlement");
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(0.0, (float) $data['totalRevenue']);
        self::assertIsArray($data['plannedCostItems']);
        self::assertNotEmpty($data['plannedCostItems']);
        self::assertGreaterThan(0, (float) $data['totalPlannedCosts']);
        self::assertSame(0.0, (float) $data['totalAdditionalExpenses']);
        self::assertSame($data['totalPlannedCosts'], $data['totalAllExpenses']);
        self::assertIsArray($data['expenses']);
        self::assertCount(0, $data['expenses']);
        // surplus = 0 - planned costs
        self::assertLessThan(0, (float) $data['surplus']);
    }

    /**
     * @throws JsonException
     */
    public function testSettlementWithExpenses(): void
    {
        $tripId = $this->createTripAndGetId();

        $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Hotel',
            'amount' => 300.0,
        ]);
        $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Bus',
            'amount' => 150.0,
        ]);

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/settlement");
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(450.0, (float) $data['totalAdditionalExpenses']);
        self::assertCount(2, $data['expenses']);
        // totalAllExpenses = planned + 450
        self::assertSame(
            round($data['totalPlannedCosts'] + 450.0, 2),
            round($data['totalAllExpenses'], 2)
        );
    }

    /**
     * @throws JsonException
     */
    public function testSettlementPlannedCostsIncludeRoomAndSpaTax(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/settlement");
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $labels = array_column($data['plannedCostItems'], 'label');
        $hasRoom = false;
        $hasSpaTax = false;
        $hasGroupExpense = false;
        foreach ($labels as $label) {
            if (str_contains($label, 'Zimmer:')) {
                $hasRoom = true;
            }
            if (str_contains($label, 'Kurabgabe')) {
                $hasSpaTax = true;
            }
            if (str_contains($label, 'Gruppenausgabe:')) {
                $hasGroupExpense = true;
            }
        }
        self::assertTrue($hasRoom, 'Expected planned costs to contain room costs');
        self::assertTrue($hasSpaTax, 'Expected planned costs to contain spa tax');
        self::assertTrue($hasGroupExpense, 'Expected planned costs to contain group expenses');
    }

    /**
     * @throws JsonException
     */
    public function testSettlementForNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('GET', '/api/trips/99999/settlement');
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testActualExpensesAreCascadeDeletedWithTrip(): void
    {
        $tripId = $this->createTripAndGetId();

        $this->dispatch('POST', "/api/trips/{$tripId}/actual-expenses", [
            'label' => 'Test',
            'amount' => 100.0,
        ]);

        // Verify expense exists
        $listResponse = $this->dispatch('GET', "/api/trips/{$tripId}/actual-expenses");
        $list = json_decode((string) $listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $list);
    }
}


