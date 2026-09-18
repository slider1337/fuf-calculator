<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;

final class TripListApiIntegrationTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    private function createTripAndGetId(): int
    {
        $response = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $response->getStatusCode(), 'Response body: ' . $response->getBody());

        $created = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return (int) $created['id'];
    }

    /**
     * @throws JsonException
     */
    private function fetchListItem(int $tripId): array
    {
        $response = $this->dispatch('GET', '/api/trips');
        self::assertSame(200, $response->getStatusCode());

        $list = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($list as $item) {
            if ($item['id'] === $tripId) {
                return $item;
            }
        }

        self::fail('Trip ' . $tripId . ' missing from list.');
    }

    /**
     * @throws JsonException
     */
    public function testListItemCarriesPeriodAndParticipantCounts(): void
    {
        $tripId = $this->createTripAndGetId();

        $item = $this->fetchListItem($tripId);

        self::assertSame('2026-12-20', $item['startDate']);
        self::assertSame('2026-12-21', $item['endDate']);
        self::assertSame(1, $item['nights']);
        self::assertSame(6, $item['plannedParticipants']);
        self::assertSame(0, $item['registeredParticipants']);
    }

    /**
     * @throws JsonException
     */
    public function testSurplusIsBasedOnTheCalculationWhileNoRegistrationsExist(): void
    {
        $tripId = $this->createTripAndGetId();

        $calculate = $this->dispatch('POST', '/api/trips/' . $tripId . '/calculate');
        self::assertSame(200, $calculate->getStatusCode());
        $calculation = json_decode((string) $calculate->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $item = $this->fetchListItem($tripId);

        self::assertSame('calculation', $item['surplusBasis']);
        self::assertSame($calculation['surplus'], $item['surplus']);
    }

    /**
     * @throws JsonException
     */
    public function testSurplusSwitchesToSettlementOnceARegistrationExists(): void
    {
        $tripId = $this->createTripAndGetId();

        $registration = $this->dispatch('POST', '/api/trips/' . $tripId . '/registrations', [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [
                ['name' => 'Max Mustermann', 'birthDate' => '1985-03-07'],
                ['name' => 'Lisa Mustermann', 'birthDate' => '2015-04-08'],
            ],
        ]);
        self::assertSame(201, $registration->getStatusCode(), 'Response body: ' . $registration->getBody());

        $settlementResponse = $this->dispatch('GET', '/api/trips/' . $tripId . '/settlement');
        self::assertSame(200, $settlementResponse->getStatusCode());
        $settlement = json_decode((string) $settlementResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $item = $this->fetchListItem($tripId);

        self::assertSame(2, $item['registeredParticipants']);
        self::assertSame('settlement', $item['surplusBasis']);
        self::assertSame($settlement['surplus'], $item['surplus']);
    }
}
