<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class RegistrationApiIntegrationTest extends ApiTestCase
{
    private function sampleCsv(): string
    {
        return implode("\n", [
            '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1";"Vor- und Nachname Teilnehmer 2";"Geburtsdatum Teilnehmer 2";"Vor- und Nachname Teilnehmer 3";"Geburtsdatum Teilnehmer 3";"Vor- und Nachname Teilnehmer 4";"Geburtsdatum Teilnehmer 4";"Vor- und Nachname Teilnehmer 5";"Geburtsdatum Teilnehmer 5";"Buchung";"Aufsicht";"Fotos";"Kommentar"',
            '"3-Bettzimmer";"28.09.2025 21:34:11";"Anna Testperson";"07.03.1985";"Tim Testperson";"08.04.2015";"Lena Testperson";"05.10.2017";"";"";"";"";"Ja";"Ja";"Ja";""',
            '"2-Bettzimmer";"29.09.2025 12:29:00";"Fritz Beispiel";"13.05.1980";"Maria Beispiel";"01.02.1976";"";"";"";"";"";"";"Ja";"Ja";"Ja";""',
        ]);
    }

    /**
     * @throws JsonException
     */
    private function createTripAndGetId(): int
    {
        $response = $this->dispatch('POST', '/api/trips', $this->validTripPayload());
        self::assertSame(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return (int) $body['id'];
    }

    private function dispatchCsvImport(int $tripId, string $csvContent): ResponseInterface
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tmpFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'anmeldungen.csv',
            'text/csv',
            strlen($csvContent),
            UPLOAD_ERR_OK
        );

        $request = new ServerRequestFactory()
            ->createServerRequest('POST', "/api/trips/{$tripId}/registrations/import")
            ->withUploadedFiles(['csv_file' => $uploadedFile])
            ->withHeader('Content-Type', 'multipart/form-data');

        return $this->app->handle($request);
    }

    /**
     * @throws JsonException
     */
    public function testImportCsvCreatesRegistrations(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatchCsvImport($tripId, $this->sampleCsv());

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $data);
    }

    /**
     * @throws JsonException
     */
    public function testImportCsvIncludesBillingData(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatchCsvImport($tripId, $this->sampleCsv());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data as $reg) {
            self::assertNotNull($reg['billingTotal']);
            self::assertNotEmpty($reg['billingItems']);
            self::assertNotNull($reg['billingCalculatedAt']);
        }
    }

    /**
     * @throws JsonException
     */
    public function testImportCsvParsesParticipantsCorrectly(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatchCsvImport($tripId, $this->sampleCsv());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $firstReg = $data[0];
        self::assertSame('3-Bettzimmer', $firstReg['roomCategory']);
        self::assertCount(3, $firstReg['participants']);
        self::assertSame('Anna Testperson', $firstReg['participants'][0]['name']);
        self::assertSame('1985-03-07', $firstReg['participants'][0]['birthDate']);
    }

    /**
     * @throws JsonException
     */
    public function testImportCsvReplacesExistingRegistrations(): void
    {
        $tripId = $this->createTripAndGetId();
        $this->dispatchCsvImport($tripId, $this->sampleCsv());
        $response = $this->dispatchCsvImport($tripId, $this->sampleCsv());

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $data);
    }

    /**
     * @throws JsonException
     */
    public function testListRegistrations(): void
    {
        $tripId = $this->createTripAndGetId();
        $this->dispatchCsvImport($tripId, $this->sampleCsv());

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/registrations");
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $data);
    }

    /**
     * @throws JsonException
     */
    public function testListRegistrationsEmptyForNewTrip(): void
    {
        $tripId = $this->createTripAndGetId();

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/registrations");
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $data);
    }

    /**
     * @throws JsonException
     */
    public function testListRegistrationsForNonExistentTripReturns404(): void
    {
        $response = $this->dispatch('GET', '/api/trips/9999/registrations');
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testRecalculateBillings(): void
    {
        $tripId = $this->createTripAndGetId();
        $this->dispatchCsvImport($tripId, $this->sampleCsv());

        $response = $this->dispatch('POST', "/api/trips/{$tripId}/registrations/recalculate");
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $data);

        foreach ($data as $reg) {
            self::assertNotNull($reg['billingTotal']);
            self::assertNotEmpty($reg['billingItems']);
        }
    }

    /**
     * @throws JsonException
     */
    public function testDeleteRegistrations(): void
    {
        $tripId = $this->createTripAndGetId();
        $this->dispatchCsvImport($tripId, $this->sampleCsv());

        $deleteResponse = $this->dispatch('DELETE', "/api/trips/{$tripId}/registrations");
        self::assertSame(200, $deleteResponse->getStatusCode());

        $listResponse = $this->dispatch('GET', "/api/trips/{$tripId}/registrations");
        $data = json_decode((string) $listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $data);
    }

    public function testImportCsvForNonExistentTripReturns404(): void
    {
        $response = $this->dispatchCsvImport(9999, $this->sampleCsv());
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testImportCsvWithoutFileReturns422(): void
    {
        $tripId = $this->createTripAndGetId();

        $request = new ServerRequestFactory()
            ->createServerRequest('POST', "/api/trips/{$tripId}/registrations/import")
            ->withHeader('Content-Type', 'multipart/form-data');

        $response = $this->app->handle($request);
        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testBillingCategoriesAreCorrectlyAssigned(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatchCsvImport($tripId, $this->sampleCsv());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $firstRegItems = $data[0]['billingItems'];
        self::assertSame('ADULT_MULTI', $firstRegItems[0]['categoryType']);
        self::assertSame('CHILD', $firstRegItems[1]['categoryType']);
        self::assertSame('CHILD', $firstRegItems[2]['categoryType']);

        $secondRegItems = $data[1]['billingItems'];
        self::assertSame('ADULT_DOUBLE', $secondRegItems[0]['categoryType']);
        self::assertSame('ADULT_DOUBLE', $secondRegItems[1]['categoryType']);
    }

    /**
     * @throws JsonException
     */
    public function testBillingPricesMatchTripCalculation(): void
    {
        $tripId = $this->createTripAndGetId();
        $this->dispatchCsvImport($tripId, $this->sampleCsv());

        $calcResponse = $this->dispatch('POST', "/api/trips/{$tripId}/calculate");
        $calcData = json_decode((string) $calcResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $pricesPerCategory = $calcData['pricesPerCategory'];

        $regResponse = $this->dispatch('GET', "/api/trips/{$tripId}/registrations");
        $regData = json_decode((string) $regResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($regData as $reg) {
            foreach ($reg['billingItems'] as $item) {
                $expectedPrice = $pricesPerCategory[$item['categoryType']];
                self::assertSame($expectedPrice, $item['price'],
                    "Price for {$item['participantName']} ({$item['categoryType']}) should match trip calculation");
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function testCreateManualRegistration(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatch('POST', "/api/trips/{$tripId}/registrations", [
            'roomCategory' => '3-Bettzimmer',
            'comment' => 'Manuell hinzugefügt',
            'participants' => [
                ['name' => 'Max Mustermann', 'birthDate' => '1985-03-07'],
                ['name' => 'Lisa Mustermann', 'birthDate' => '2015-04-08'],
            ],
        ]);

        self::assertSame(201, $response->getStatusCode(), 'Response body: ' . $response->getBody());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
        self::assertSame('manual', $data[0]['source']);
        self::assertSame('3-Bettzimmer', $data[0]['roomCategory']);
        self::assertCount(2, $data[0]['participants']);
    }

    /**
     * @throws JsonException
     */
    public function testCreateManualRegistrationValidation(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatch('POST', "/api/trips/{$tripId}/registrations", [
            'roomCategory' => '',
            'participants' => [],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testCreateManualRegistrationForUnknownTripReturns404(): void
    {
        $response = $this->dispatch('POST', '/api/trips/9999/registrations', [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [['name' => 'Max', 'birthDate' => '1985-03-07']],
        ]);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testCsvImportPreservesManualRegistrations(): void
    {
        $tripId = $this->createTripAndGetId();

        // Add manual registration
        $createResponse = $this->dispatch('POST', "/api/trips/{$tripId}/registrations", [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [
                ['name' => 'Manual Person', 'birthDate' => '1990-01-01'],
            ],
        ]);
        self::assertSame(201, $createResponse->getStatusCode(), 'Create failed: ' . $createResponse->getBody());

        // Import CSV
        $importResponse = $this->dispatchCsvImport($tripId, $this->sampleCsv());
        self::assertSame(201, $importResponse->getStatusCode());
        $data = json_decode((string) $importResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        // 1 manual + 2 CSV = 3
        self::assertCount(3, $data);
        $sources = array_column($data, 'source');
        self::assertContains('manual', $sources);
        self::assertContains('csv', $sources);
    }

    /**
     * @throws JsonException
     */
    public function testDeleteSingleRegistration(): void
    {
        $tripId = $this->createTripAndGetId();
        $importResponse = $this->dispatchCsvImport($tripId, $this->sampleCsv());
        $importData = json_decode((string) $importResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $regId = $importData[0]['id'];

        $deleteResponse = $this->dispatch('DELETE', "/api/trips/{$tripId}/registrations/{$regId}");
        self::assertSame(200, $deleteResponse->getStatusCode());

        $data = json_decode((string) $deleteResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
    }

    /**
     * @throws JsonException
     */
    public function testDeleteSingleRegistrationNotFound(): void
    {
        $tripId = $this->createTripAndGetId();
        $response = $this->dispatch('DELETE', "/api/trips/{$tripId}/registrations/9999");
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testRegistrationsIncludeSourceField(): void
    {
        $tripId = $this->createTripAndGetId();
        $this->dispatchCsvImport($tripId, $this->sampleCsv());

        $response = $this->dispatch('GET', "/api/trips/{$tripId}/registrations");
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data as $reg) {
            self::assertArrayHasKey('source', $reg);
            self::assertSame('csv', $reg['source']);
        }
    }
}

