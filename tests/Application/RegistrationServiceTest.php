<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\NotFoundException;
use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\RegistrationService;
use App\Application\ValidationException;
use App\Domain\Registration\Registration;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Service\RegistrationBillingService;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    private InMemoryRegistrationRepository $registrationRepo;
    private InMemoryTripRepository $tripRepo;
    private RegistrationService $service;

    protected function setUp(): void
    {
        $this->registrationRepo = new InMemoryRegistrationRepository();
        $this->tripRepo = new InMemoryTripRepository();
        $billingService = new RegistrationBillingService(new PriceCalculatorService());
        $this->service = new RegistrationService($this->registrationRepo, $this->tripRepo, $billingService);
    }

    private function createTrip(): Trip
    {
        return $this->tripRepo->save(new Trip(
            null,
            'Winterfreizeit 2026',
            new DateTimeImmutable('2026-02-14'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.50,
                18
            ),
            [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 4, 100.00),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, 6, 90.00),
                new RoomBooking(RoomCategoryType::CHILD, 5, 60.00),
            ],
            [new GroupExpense('Getraenke', 75.00)],
            700.00,
            800.00
        ));
    }

    private function sampleCsv(): string
    {
        return implode("\n", [
            '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1";"Vor- und Nachname Teilnehmer 2";"Geburtsdatum Teilnehmer 2";"Vor- und Nachname Teilnehmer 3";"Geburtsdatum Teilnehmer 3";"Vor- und Nachname Teilnehmer 4";"Geburtsdatum Teilnehmer 4";"Vor- und Nachname Teilnehmer 5";"Geburtsdatum Teilnehmer 5";"Buchung";"Aufsicht";"Fotos";"Kommentar"',
            '"3-Bettzimmer";"28.09.2025 21:34:11";"Anna Testperson";"07.03.1985";"Tim Testperson";"08.04.2015";"Lena Testperson";"05.10.2017";"";"";"";"";"Ja";"Ja";"Ja";""',
            '"2-Bettzimmer";"29.09.2025 12:29:00";"Fritz Beispiel";"13.05.1980";"Maria Beispiel";"01.02.1976";"";"";"";"";"";"";"Ja";"Ja";"Ja";""',
        ]);
    }

    public function testImportCsvCreatesRegistrations(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        self::assertCount(2, $result);
        self::assertSame('3-Bettzimmer', $result[0]->roomCategory());
        self::assertCount(3, $result[0]->participants());
        self::assertSame('2-Bettzimmer', $result[1]->roomCategory());
        self::assertCount(2, $result[1]->participants());
    }

    public function testImportCsvCalculatesBillings(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        foreach ($result as $reg) {
            self::assertNotNull($reg->billingCalculatedAt());
            self::assertNotNull($reg->billingTotal());
            self::assertGreaterThan(0, $reg->billingTotal());
            self::assertNotEmpty($reg->billingItems());
        }
    }

    public function testImportCsvReplacesExistingRegistrations(): void
    {
        $trip = $this->createTrip();
        $this->service->importCsv($trip->id(), $this->sampleCsv());
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        self::assertCount(2, $result);
    }

    public function testImportCsvWithUnknownTripThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->importCsv(9999, $this->sampleCsv());
    }

    public function testImportCsvWithEmptyContentThrowsValidation(): void
    {
        $trip = $this->createTrip();

        $this->expectException(ValidationException::class);
        $this->service->importCsv($trip->id(), '');
    }

    public function testImportCsvWithHeaderOnlyThrowsValidation(): void
    {
        $trip = $this->createTrip();
        $headerOnly = '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1";"Kommentar"';

        $this->expectException(ValidationException::class);
        $this->service->importCsv($trip->id(), $headerOnly);
    }

    public function testGetRegistrationsReturnsEmpty(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->getRegistrations($trip->id());

        self::assertSame([], $result);
    }

    public function testGetRegistrationsForUnknownTripThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->getRegistrations(9999);
    }

    public function testRecalculateBillings(): void
    {
        $trip = $this->createTrip();
        $this->service->importCsv($trip->id(), $this->sampleCsv());
        $result = $this->service->recalculateBillings($trip->id());

        self::assertCount(2, $result);
        foreach ($result as $reg) {
            self::assertNotNull($reg->billingTotal());
        }
    }

    public function testDeleteRegistrations(): void
    {
        $trip = $this->createTrip();
        $this->service->importCsv($trip->id(), $this->sampleCsv());
        $this->service->deleteRegistrations($trip->id());

        $result = $this->service->getRegistrations($trip->id());
        self::assertSame([], $result);
    }

    public function testDeleteRegistrationsForUnknownTripThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->deleteRegistrations(9999);
    }

    public function testImportCsvParsesGermanDatesCorrectly(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        $participants = $result[0]->participants();
        self::assertSame('1985-03-07', $participants[0]->birthDate()->format('Y-m-d'));
        self::assertSame('2015-04-08', $participants[1]->birthDate()->format('Y-m-d'));
    }

    public function testImportCsvSkipsEmptyParticipants(): void
    {
        $trip = $this->createTrip();
        $csv = implode("\n", [
            '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1";"Vor- und Nachname Teilnehmer 2";"Geburtsdatum Teilnehmer 2";"Kommentar"',
            '"2-Bettzimmer";"01.01.2025";"Max Mustermann";"01.01.1980";"";"";"Test"',
        ]);

        $result = $this->service->importCsv($trip->id(), $csv);

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]->participants());
    }

    public function testImportCsvWithExtraColumnsFindsAllParticipants(): void
    {
        $trip = $this->createTrip();
        $csv = implode("\n", [
            '"Rechnung gestellt";"Zimmerkategorie";"Erhalten am";"Gesamtkosten";"Vor- und Nachname Teilnehmer 1";"Teilnehmerbetrag bezahlt?";"Geburtsdatum Teilnehmer 1";"Alle Teilnehmer Mitglieder in FuF?";"Vor- und Nachname Teilnehmer 2";"Geburtsdatum Teilnehmer 2";"Vor- und Nachname Teilnehmer 3";"Geburtsdatum Teilnehmer 3";"Vor- und Nachname Teilnehmer 4";"Geburtsdatum Teilnehmer 4";"Vor- und Nachname Teilnehmer 5";"Geburtsdatum Teilnehmer 5";"Buchung";"Aufsicht";"Fotos";"Kommentar"',
            '"Ja";"4-Bettzimmer";"01.10.2024 21:19:07";"1315";"Petra Musterfrau";"Ja";"23.08.1982";"Ja";"Hans Musterfrau";"07.02.1974";"Sophie Musterfrau";"07.10.2008";"Paul Musterfrau";"25.10.2013";"";"";"Ja";"Ja";"Ja";""',
            '"Ja";"5-Bettzimmer";"02.10.2024 08:48:34";"1565";"Klaus Testmann";"Ja";"18.11.1975";"Nein";"Eva Testmann";"06.02.1981";"Lara Testmann";"27.05.2010";"Mia Testmann";"09.01.2013";"Leo Testmann";"06.08.2020";"Ja";"Ja";"Ja";""',
        ]);

        $result = $this->service->importCsv($trip->id(), $csv);

        self::assertCount(2, $result);
        self::assertSame('4-Bettzimmer', $result[0]->roomCategory());
        self::assertCount(4, $result[0]->participants());
        self::assertSame('Petra Musterfrau', $result[0]->participants()[0]->name());
        self::assertSame('1982-08-23', $result[0]->participants()[0]->birthDate()->format('Y-m-d'));
        self::assertSame('Hans Musterfrau', $result[0]->participants()[1]->name());
        self::assertSame('Sophie Musterfrau', $result[0]->participants()[2]->name());
        self::assertSame('Paul Musterfrau', $result[0]->participants()[3]->name());

        self::assertSame('5-Bettzimmer', $result[1]->roomCategory());
        self::assertCount(5, $result[1]->participants());
    }

    public function testImportCsvWithMultiLineCommentField(): void
    {
        $trip = $this->createTrip();
        $csv = '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1";"Vor- und Nachname Teilnehmer 2";"Geburtsdatum Teilnehmer 2";"Kommentar"' . "\n"
            . '"3-Bettzimmer";"04.10.2024";"Karl Testheim";"28.09.1978";"Monika Testheim";"29.09.1984";"Zeile 1' . "\n" . 'Zeile 2' . "\n" . 'Zeile 3"' . "\n"
            . '"2-Bettzimmer";"05.10.2024";"Max Mustermann";"01.01.1980";"";"";"OK"';

        $result = $this->service->importCsv($trip->id(), $csv);

        self::assertCount(2, $result);
        self::assertSame('3-Bettzimmer', $result[0]->roomCategory());
        self::assertCount(2, $result[0]->participants());
        self::assertStringContainsString('Zeile 1', $result[0]->comment());
        self::assertStringContainsString('Zeile 3', $result[0]->comment());

        self::assertSame('2-Bettzimmer', $result[1]->roomCategory());
        self::assertCount(1, $result[1]->participants());
    }

    public function testImportCsvWithEmptyRoomCategorySkipsRow(): void
    {
        $trip = $this->createTrip();
        $csv = implode("\n", [
            '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1";"Kommentar"',
            '"4-Bettzimmer";"01.10.2024";"Petra Musterfrau";"23.08.1982";""',
            '"";"";"";"Test Person";"10.01.1995";""',
        ]);

        $result = $this->service->importCsv($trip->id(), $csv);

        self::assertCount(1, $result);
        self::assertSame('4-Bettzimmer', $result[0]->roomCategory());
    }

    public function testImportCsvWithCommaDelimiter(): void
    {
        $trip = $this->createTrip();
        $csv = implode("\n", [
            '"Zimmerkategorie","Erhalten am","Vor- und Nachname Teilnehmer 1","Geburtsdatum Teilnehmer 1","Kommentar"',
            '"3-Bettzimmer","01.10.2024","Max Mustermann","01.01.1985","Test"',
        ]);

        $result = $this->service->importCsv($trip->id(), $csv);

        self::assertCount(1, $result);
        self::assertSame('3-Bettzimmer', $result[0]->roomCategory());
        self::assertSame('Test', $result[0]->comment());
    }

    public function testImportCsvWithoutCommentColumn(): void
    {
        $trip = $this->createTrip();
        $csv = implode("\n", [
            '"Zimmerkategorie";"Erhalten am";"Vor- und Nachname Teilnehmer 1";"Geburtsdatum Teilnehmer 1"',
            '"2-Bettzimmer";"01.10.2024";"Max Mustermann";"01.01.1985"',
        ]);

        $result = $this->service->importCsv($trip->id(), $csv);

        self::assertCount(1, $result);
        self::assertSame('', $result[0]->comment());
    }

    public function testBillingCategoriesMatchRoomAndAge(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        $items = $result[0]->billingItems();
        self::assertSame('ADULT_MULTI', $items[0]->categoryType());
        self::assertSame('CHILD', $items[1]->categoryType());
        self::assertSame('CHILD', $items[2]->categoryType());

        $items2 = $result[1]->billingItems();
        self::assertSame('ADULT_DOUBLE', $items2[0]->categoryType());
        self::assertSame('ADULT_DOUBLE', $items2[1]->categoryType());
    }

    public function testAddManualRegistration(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->addManualRegistration($trip->id(), [
            'roomCategory' => '3-Bettzimmer',
            'comment' => 'Test manual',
            'participants' => [
                ['name' => 'Max Mustermann', 'birthDate' => '1985-03-07'],
                ['name' => 'Lisa Mustermann', 'birthDate' => '2015-04-08'],
            ],
        ]);

        self::assertCount(1, $result);
        self::assertSame('3-Bettzimmer', $result[0]->roomCategory());
        self::assertSame('manual', $result[0]->source());
        self::assertCount(2, $result[0]->participants());
        self::assertSame('Test manual', $result[0]->comment());
    }

    public function testAddManualRegistrationFailsWithoutParticipants(): void
    {
        $trip = $this->createTrip();

        $this->expectException(ValidationException::class);
        $this->service->addManualRegistration($trip->id(), [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [],
        ]);
    }

    public function testAddManualRegistrationFailsWithoutRoomCategory(): void
    {
        $trip = $this->createTrip();

        $this->expectException(ValidationException::class);
        $this->service->addManualRegistration($trip->id(), [
            'roomCategory' => '',
            'participants' => [['name' => 'Max', 'birthDate' => '1985-03-07']],
        ]);
    }

    public function testAddManualRegistrationForUnknownTripThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->addManualRegistration(9999, [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [['name' => 'Max', 'birthDate' => '1985-03-07']],
        ]);
    }

    public function testCsvImportPreservesManualRegistrations(): void
    {
        $trip = $this->createTrip();

        // Add a manual registration first
        $this->service->addManualRegistration($trip->id(), [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [['name' => 'Manual Person', 'birthDate' => '1990-01-01']],
        ]);

        // Import CSV
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        // Should contain manual + CSV registrations
        self::assertCount(3, $result);
        $sources = array_map(fn ($r) => $r->source(), $result);
        self::assertContains('manual', $sources);
        self::assertContains('csv', $sources);
    }

    public function testCsvImportReplacesOnlyCsvRegistrations(): void
    {
        $trip = $this->createTrip();

        // Add manual first
        $this->service->addManualRegistration($trip->id(), [
            'roomCategory' => '2-Bettzimmer',
            'participants' => [['name' => 'Manual Person', 'birthDate' => '1990-01-01']],
        ]);

        // Import CSV twice
        $this->service->importCsv($trip->id(), $this->sampleCsv());
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        // 1 manual + 2 csv
        self::assertCount(3, $result);
    }

    public function testDeleteSingleRegistration(): void
    {
        $trip = $this->createTrip();
        $regs = $this->service->importCsv($trip->id(), $this->sampleCsv());
        $regId = $regs[0]->id();

        $result = $this->service->deleteRegistration($trip->id(), $regId);
        self::assertCount(1, $result);
    }

    public function testDeleteSingleRegistrationForWrongTripThrowsNotFound(): void
    {
        $trip = $this->createTrip();
        $trip2 = $this->tripRepo->save(new Trip(
            null,
            'Other Trip',
            new DateTimeImmutable('2027-01-01'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.50,
                18
            ),
            [new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 2, 100.00)],
            [],
            500.00,
            600.00
        ));

        $regs = $this->service->importCsv($trip->id(), $this->sampleCsv());

        $this->expectException(NotFoundException::class);
        $this->service->deleteRegistration($trip2->id(), $regs[0]->id());
    }

    public function testDeleteNonExistentRegistrationThrowsNotFound(): void
    {
        $trip = $this->createTrip();

        $this->expectException(NotFoundException::class);
        $this->service->deleteRegistration($trip->id(), 9999);
    }

    public function testCsvRegistrationsHaveCsvSource(): void
    {
        $trip = $this->createTrip();
        $result = $this->service->importCsv($trip->id(), $this->sampleCsv());

        foreach ($result as $reg) {
            self::assertSame('csv', $reg->source());
        }
    }
}

final class InMemoryRegistrationRepository implements RegistrationRepositoryInterface
{
    /** @var array<int, Registration> */
    private array $items = [];
    private int $nextId = 1;

    public function save(Registration $registration): Registration
    {
        $id = $this->nextId++;
        $saved = $registration->withId($id);
        $this->items[$id] = $saved;
        return $saved;
    }

    public function updateBilling(Registration $registration): Registration
    {
        $id = $registration->id();
        if ($id === null) {
            throw new \InvalidArgumentException('Registration id is required.');
        }
        $this->items[$id] = $registration;
        return $registration;
    }

    /** @return Registration[] */
    public function findByTripId(int $tripId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Registration $r) => $r->tripId() === $tripId
        ));
    }

    public function findById(int $id): ?Registration
    {
        return $this->items[$id] ?? null;
    }

    public function deleteByTripId(int $tripId): void
    {
        $this->items = array_filter(
            $this->items,
            static fn (Registration $r) => $r->tripId() !== $tripId
        );
    }

    public function deleteCsvByTripId(int $tripId): void
    {
        $this->items = array_filter(
            $this->items,
            static fn (Registration $r) => $r->tripId() !== $tripId || $r->source() !== 'csv'
        );
    }

    public function deleteById(int $id): void
    {
        unset($this->items[$id]);
    }
}











