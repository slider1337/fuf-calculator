<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\Port\TripRepositoryInterface;
use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use App\Domain\Service\RegistrationBillingService;
use DateMalformedStringException;
use DateTimeImmutable;

final readonly class RegistrationService
{
    public function __construct(
        private RegistrationRepositoryInterface $registrationRepository,
        private TripRepositoryInterface $tripRepository,
        private RegistrationBillingService $billingService
    ) {
    }

    /**
     * Import registrations from CSV content. Replaces only CSV-sourced registrations; manual ones are preserved.
     *
     * @return Registration[]
     * @throws DateMalformedStringException
     */
    public function importCsv(int $tripId, string $csvContent): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $registrations = $this->parseCsv($tripId, $csvContent);

        if ($registrations === []) {
            throw new ValidationException(['csv' => 'no_valid_registrations_found']);
        }

        $this->registrationRepository->deleteCsvByTripId($tripId);

        foreach ($registrations as $registration) {
            $this->registrationRepository->save($registration);
        }

        return $this->recalculateBillings($tripId);
    }

    /**
     * Add a manual registration.
     *
     * @param array{roomCategory: string, comment?: string, participants: array<int, array{name: string, birthDate: string}>} $data
     * @return Registration[]
     */
    public function addManualRegistration(int $tripId, array $data): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $errors = [];
        $roomCategory = trim($data['roomCategory'] ?? '');
        if ($roomCategory === '') {
            $errors['roomCategory'] = 'required';
        }

        $participantsData = $data['participants'] ?? [];
        if (!is_array($participantsData) || count($participantsData) === 0) {
            $errors['participants'] = 'at_least_one_required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $participants = [];
        foreach ($participantsData as $i => $pData) {
            $name = trim($pData['name'] ?? '');
            $birthDateStr = trim($pData['birthDate'] ?? '');

            if ($name === '' || $birthDateStr === '') {
                continue;
            }

            $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthDateStr);
            if ($birthDate === false) {
                $errors["participants.$i.birthDate"] = 'invalid_date';
                continue;
            }

            $participants[] = new Participant($name, $birthDate);
        }

        if ($participants === []) {
            throw new ValidationException(['participants' => 'at_least_one_valid_required']);
        }

        $registration = new Registration(
            id: null,
            tripId: $tripId,
            roomCategory: $roomCategory,
            receivedAt: date('d.m.Y H:i:s'),
            comment: trim($data['comment'] ?? ''),
            participants: $participants,
            source: Registration::SOURCE_MANUAL
        );

        $this->registrationRepository->save($registration);

        return $this->recalculateBillings($tripId);
    }

    /**
     * Delete a single registration by id.
     *
     * @return Registration[]
     */
    public function deleteRegistration(int $tripId, int $registrationId): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $registration = $this->registrationRepository->findById($registrationId);
        if ($registration === null || $registration->tripId() !== $tripId) {
            throw new NotFoundException('Registration not found.');
        }

        $this->registrationRepository->deleteById($registrationId);

        return $this->recalculateBillings($tripId);
    }

    /**
     * @return Registration[]
     */
    public function getRegistrations(int $tripId): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        return $this->registrationRepository->findByTripId($tripId);
    }

    /**
     * @return Registration[]
     */
    public function recalculateBillings(int $tripId): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $registrations = $this->registrationRepository->findByTripId($tripId);

        $updatedRegistrations = $this->billingService->calculateBillings($trip, $registrations);

        $result = [];
        foreach ($updatedRegistrations as $updated) {
            $result[] = $this->registrationRepository->updateBilling($updated);
        }

        return $result;
    }

    public function deleteRegistrations(int $tripId): void
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $this->registrationRepository->deleteByTripId($tripId);
    }

    /**
     * @return Registration[]
     * @throws DateMalformedStringException
     */
    private function parseCsv(int $tripId, string $csvContent): array
    {
        $csvContent = $this->normalizeEncoding($csvContent);

        $delimiter = $this->detectDelimiter($csvContent);

        // Use a temp stream + fgetcsv to correctly handle multi-line quoted fields
        $stream = fopen('php://temp', 'rb+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $csvContent);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if ($header === false || count($header) < 2) {
            fclose($stream);
            return [];
        }

        $columnMap = $this->buildColumnMap($header);
        if ($columnMap === null) {
            fclose($stream);
            return [];
        }

        $registrations = [];

        while (($fields = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if (count($fields) < 2) {
                continue;
            }

            $registration = $this->parseRegistrationRow($tripId, $fields, $columnMap);
            if ($registration !== null) {
                $registrations[] = $registration;
            }
        }

        fclose($stream);

        return $registrations;
    }

    /**
     * Detect CSV delimiter by analyzing the first line.
     */
    private function detectDelimiter(string $csvContent): string
    {
        // Extract first line (before any newline) for delimiter detection
        $firstLineEnd = strpos($csvContent, "\n");
        $firstLine = $firstLineEnd !== false ? substr($csvContent, 0, $firstLineEnd) : $csvContent;

        $delimiters = [';' => 0, ',' => 0, "\t" => 0];
        foreach ($delimiters as $d => &$count) {
            $count = substr_count($firstLine, $d);
        }
        unset($count);

        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    /**
     * Build a column map from header names using pattern matching.
     *
     * @param string[] $header
     * @return array{roomCategory: int, receivedAt: ?int, comment: ?int, participants: array<int, array{name: int, birthDate: int}>}|null
     */
    private function buildColumnMap(array $header): ?array
    {
        $normalized = array_map(static fn(string $h) => mb_strtolower(trim($h)), $header);

        // Find room category column
        $roomCategoryIdx = $this->findColumnIndex($normalized, ['zimmerkategorie', 'room category', 'zimmer']);
        if ($roomCategoryIdx === null) {
            return null;
        }

        // Find received at column
        $receivedAtIdx = $this->findColumnIndex($normalized, ['erhalten am', 'received at', 'eingegangen']);

        // Find comment column
        $commentIdx = $this->findColumnIndex($normalized, ['kommentar', 'comment', 'anmerkung', 'bemerkung']);

        // Find participant columns (up to 5 participants)
        $participants = [];
        for ($p = 1; $p <= 5; $p++) {
            $nameIdx = $this->findColumnIndex($normalized, [
                "vor- und nachname teilnehmer $p",
                "name teilnehmer $p",
                "teilnehmer $p name",
            ]);

            $birthDateIdx = $this->findColumnIndex($normalized, [
                "geburtsdatum teilnehmer $p",
                "geb teilnehmer $p",
                "geburtstag teilnehmer $p",
            ]);

            if ($nameIdx !== null && $birthDateIdx !== null) {
                $participants[$p] = ['name' => $nameIdx, 'birthDate' => $birthDateIdx];
            }
        }

        if ($participants === []) {
            return null;
        }

        return [
            'roomCategory' => $roomCategoryIdx,
            'receivedAt' => $receivedAtIdx,
            'comment' => $commentIdx,
            'participants' => $participants,
        ];
    }

    /**
     * Find a column index by matching against multiple possible header names.
     *
     * @param string[] $normalizedHeaders
     * @param string[] $patterns
     */
    private function findColumnIndex(array $normalizedHeaders, array $patterns): ?int
    {
        // First try exact match
        foreach ($patterns as $pattern) {
            $idx = array_search($pattern, $normalizedHeaders, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        // Then try contains match
        foreach ($patterns as $pattern) {
            foreach ($normalizedHeaders as $idx => $header) {
                if (str_contains($header, $pattern)) {
                    return (int) $idx;
                }
            }
        }

        return null;
    }

    /**
     * @param array{roomCategory: int, receivedAt: ?int, comment: ?int, participants: array<int, array{name: int, birthDate: int}>} $columnMap
     * @throws DateMalformedStringException
     */
    private function parseRegistrationRow(int $tripId, array $fields, array $columnMap): ?Registration
    {
        $roomCategory = trim($fields[$columnMap['roomCategory']] ?? '');
        $receivedAt = $columnMap['receivedAt'] !== null ? trim($fields[$columnMap['receivedAt']] ?? '') : '';
        $comment = $columnMap['comment'] !== null ? trim($fields[$columnMap['comment']] ?? '') : '';

        if ($roomCategory === '') {
            return null;
        }

        $participants = [];

        foreach ($columnMap['participants'] as $pMap) {
            $name = trim($fields[$pMap['name']] ?? '');
            $birthDateStr = trim($fields[$pMap['birthDate']] ?? '');

            if ($name === '' || $birthDateStr === '') {
                continue;
            }

            $birthDate = $this->parseGermanDate($birthDateStr);
            if ($birthDate === null) {
                continue;
            }

            $participants[] = new Participant($name, $birthDate);
        }

        if ($participants === []) {
            return null;
        }

        return new Registration(
            id: null,
            tripId: $tripId,
            roomCategory: $roomCategory,
            receivedAt: $receivedAt,
            comment: $comment,
            participants: $participants
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    private function parseGermanDate(string $dateStr): ?DateTimeImmutable
    {
        $dateStr = trim($dateStr);

        $match = preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $dateStr, $matches);
        if ($match !== 1) {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private function normalizeEncoding(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        if ($encoding !== false && $encoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
            if (is_string($converted)) {
                return $converted;
            }
        }

        return $content;
    }
}




