<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\RegistrationRepositoryInterface;
use App\Domain\Registration\BillingLineItem;
use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final readonly class SqliteRegistrationRepository implements RegistrationRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Registration $registration): Registration
    {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            'INSERT INTO registrations (trip_id, room_category, received_at, comment, source, billing_calculated_at, billing_total)
             VALUES (:tripId, :roomCategory, :receivedAt, :comment, :source, :billingCalc, :billingTotal)'
        );

        $stmt->execute([
            ':tripId' => $registration->tripId(),
            ':roomCategory' => $registration->roomCategory(),
            ':receivedAt' => $registration->receivedAt(),
            ':comment' => $registration->comment(),
            ':source' => $registration->source(),
            ':billingCalc' => $registration->billingCalculatedAt()?->format('Y-m-d H:i:s'),
            ':billingTotal' => $registration->billingTotal(),
        ]);

        $registrationId = (int) $this->pdo->lastInsertId();

        $participantStmt = $this->pdo->prepare(
            'INSERT INTO registration_participants (registration_id, name, birth_date)
             VALUES (:registrationId, :name, :birthDate)'
        );

        foreach ($registration->participants() as $participant) {
            $participantStmt->execute([
                ':registrationId' => $registrationId,
                ':name' => $participant->name(),
                ':birthDate' => $participant->birthDate()->format('Y-m-d'),
            ]);
        }

        $this->persistBillingItems($registrationId, $registration->billingItems());

        $this->pdo->commit();

        return $registration->withId($registrationId);
    }

    public function updateBilling(Registration $registration): Registration
    {
        $id = $registration->id();
        if ($id === null) {
            throw new InvalidArgumentException('Registration id is required for billing update.');
        }

        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            'UPDATE registrations SET billing_calculated_at = :billingCalc, billing_total = :billingTotal WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $id,
            ':billingCalc' => $registration->billingCalculatedAt()?->format('Y-m-d H:i:s'),
            ':billingTotal' => $registration->billingTotal(),
        ]);

        $this->pdo->prepare('DELETE FROM registration_billing_items WHERE registration_id = :id')
            ->execute([':id' => $id]);

        $this->persistBillingItems($id, $registration->billingItems());

        $this->pdo->commit();

        return $registration;
    }

    /**
     * @return Registration[]
     * @throws DateMalformedStringException
     */
    public function findByTripId(int $tripId): array
    {
        $regStmt = $this->pdo->prepare(
            'SELECT * FROM registrations WHERE trip_id = :tripId ORDER BY id ASC'
        );
        $regStmt->execute([':tripId' => $tripId]);
        $regRows = $regStmt->fetchAll();

        $registrations = [];
        foreach ($regRows as $row) {
            $registrationId = (int) $row['id'];

            $participants = $this->loadParticipants($registrationId);
            $billingItems = $this->loadBillingItems($registrationId);

            $billingCalc = $row['billing_calculated_at'] !== null
                ? new DateTimeImmutable((string) $row['billing_calculated_at'])
                : null;

            $registrations[] = new Registration(
                id: $registrationId,
                tripId: (int) $row['trip_id'],
                roomCategory: (string) $row['room_category'],
                receivedAt: $row['received_at'] !== null ? (string) $row['received_at'] : null,
                comment: (string) $row['comment'],
                participants: $participants,
                source: (string) ($row['source'] ?? Registration::SOURCE_CSV),
                billingCalculatedAt: $billingCalc,
                billingTotal: $row['billing_total'] !== null ? (float) $row['billing_total'] : null,
                billingItems: $billingItems
            );
        }

        return $registrations;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?Registration
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registrations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $registrationId = (int) $row['id'];
        $participants = $this->loadParticipants($registrationId);
        $billingItems = $this->loadBillingItems($registrationId);
        $billingCalc = $row['billing_calculated_at'] !== null
            ? new DateTimeImmutable((string) $row['billing_calculated_at'])
            : null;

        return new Registration(
            id: $registrationId,
            tripId: (int) $row['trip_id'],
            roomCategory: (string) $row['room_category'],
            receivedAt: $row['received_at'] !== null ? (string) $row['received_at'] : null,
            comment: (string) $row['comment'],
            participants: $participants,
            source: (string) ($row['source'] ?? Registration::SOURCE_CSV),
            billingCalculatedAt: $billingCalc,
            billingTotal: $row['billing_total'] !== null ? (float) $row['billing_total'] : null,
            billingItems: $billingItems
        );
    }

    public function deleteByTripId(int $tripId): void
    {
        $regIds = $this->pdo->prepare('SELECT id FROM registrations WHERE trip_id = :tripId');
        $regIds->execute([':tripId' => $tripId]);
        $ids = $regIds->fetchAll(PDO::FETCH_COLUMN);

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $this->pdo->prepare("DELETE FROM registration_billing_items WHERE registration_id IN ($placeholders)")
                ->execute($ids);

            $this->pdo->prepare("DELETE FROM registration_participants WHERE registration_id IN ($placeholders)")
                ->execute($ids);
        }

        $this->pdo->prepare('DELETE FROM registrations WHERE trip_id = :tripId')
            ->execute([':tripId' => $tripId]);
    }

    public function deleteCsvByTripId(int $tripId): void
    {
        $regIds = $this->pdo->prepare("SELECT id FROM registrations WHERE trip_id = :tripId AND source = 'csv'");
        $regIds->execute([':tripId' => $tripId]);
        $ids = $regIds->fetchAll(PDO::FETCH_COLUMN);

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $this->pdo->prepare("DELETE FROM registration_billing_items WHERE registration_id IN ($placeholders)")
                ->execute($ids);

            $this->pdo->prepare("DELETE FROM registration_participants WHERE registration_id IN ($placeholders)")
                ->execute($ids);
        }

        $this->pdo->prepare("DELETE FROM registrations WHERE trip_id = :tripId AND source = 'csv'")
            ->execute([':tripId' => $tripId]);
    }

    public function deleteById(int $id): void
    {
        $this->pdo->prepare('DELETE FROM registration_billing_items WHERE registration_id = :id')
            ->execute([':id' => $id]);

        $this->pdo->prepare('DELETE FROM registration_participants WHERE registration_id = :id')
            ->execute([':id' => $id]);

        $this->pdo->prepare('DELETE FROM registrations WHERE id = :id')
            ->execute([':id' => $id]);
    }

    /**
     * @return Participant[]
     * @throws DateMalformedStringException
     */
    private function loadParticipants(int $registrationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM registration_participants WHERE registration_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $registrationId]);

        $participants = [];
        foreach ($stmt->fetchAll() as $row) {
            $participants[] = new Participant(
                (string) $row['name'],
                new DateTimeImmutable((string) $row['birth_date'])
            );
        }

        return $participants;
    }

    /** @return BillingLineItem[] */
    private function loadBillingItems(int $registrationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM registration_billing_items WHERE registration_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $registrationId]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = new BillingLineItem(
                (string) $row['participant_name'],
                (string) $row['category_type'],
                (float) $row['price']
            );
        }

        return $items;
    }

    /** @param BillingLineItem[] $items */
    private function persistBillingItems(int $registrationId, array $items): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO registration_billing_items (registration_id, participant_name, category_type, price)
             VALUES (:regId, :name, :category, :price)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                ':regId' => $registrationId,
                ':name' => $item->participantName(),
                ':category' => $item->categoryType(),
                ':price' => $item->price(),
            ]);
        }
    }
}




