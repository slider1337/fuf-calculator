<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Registration\BillingLineItem;
use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RegistrationDomainTest extends TestCase
{
    public function testParticipantCreation(): void
    {
        $participant = new Participant('Max Mustermann', new DateTimeImmutable('1985-03-07'));

        self::assertSame('Max Mustermann', $participant->name());
        self::assertSame('1985-03-07', $participant->birthDate()->format('Y-m-d'));
    }

    public function testParticipantEmptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Participant('', new DateTimeImmutable('1985-03-07'));
    }

    public function testParticipantAgeAtDate(): void
    {
        $participant = new Participant('Kind', new DateTimeImmutable('2015-06-15'));

        self::assertSame(10, $participant->ageAtDate(new DateTimeImmutable('2026-02-14')));
        self::assertSame(11, $participant->ageAtDate(new DateTimeImmutable('2026-06-15')));
        self::assertSame(10, $participant->ageAtDate(new DateTimeImmutable('2026-06-14')));
    }

    public function testBillingLineItemCreation(): void
    {
        $item = new BillingLineItem('Max', 'ADULT_DOUBLE', 123.45);

        self::assertSame('Max', $item->participantName());
        self::assertSame('ADULT_DOUBLE', $item->categoryType());
        self::assertSame(123.45, $item->price());
    }

    public function testBillingLineItemRoundsPrice(): void
    {
        $item = new BillingLineItem('Max', 'CHILD', 99.999);

        self::assertSame(100.0, $item->price());
    }

    public function testRegistrationCreation(): void
    {
        $registration = new Registration(
            null,
            1,
            '3-Bettzimmer',
            '28.09.2025',
            'Kommentar',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))]
        );

        self::assertNull($registration->id());
        self::assertSame(1, $registration->tripId());
        self::assertSame('3-Bettzimmer', $registration->roomCategory());
        self::assertSame('28.09.2025', $registration->receivedAt());
        self::assertSame('Kommentar', $registration->comment());
        self::assertCount(1, $registration->participants());
        self::assertNull($registration->billingCalculatedAt());
        self::assertNull($registration->billingTotal());
        self::assertSame([], $registration->billingItems());
    }

    public function testRegistrationEmptyRoomCategoryThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Registration(null, 1, '', null, '', [
            new Participant('Max', new DateTimeImmutable('1985-01-01')),
        ]);
    }

    public function testRegistrationEmptyParticipantsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Registration(null, 1, '2-Bettzimmer', null, '', []);
    }

    public function testRegistrationWithId(): void
    {
        $registration = new Registration(
            null,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))]
        );

        $withId = $registration->withId(42);

        self::assertSame(42, $withId->id());
        self::assertSame(1, $withId->tripId());
    }

    public function testRegistrationWithBilling(): void
    {
        $registration = new Registration(
            1,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))]
        );

        $now = new DateTimeImmutable();
        $items = [new BillingLineItem('Max', 'ADULT_DOUBLE', 120.50)];
        $withBilling = $registration->withBilling($now, 120.50, $items);

        self::assertSame($now, $withBilling->billingCalculatedAt());
        self::assertSame(120.50, $withBilling->billingTotal());
        self::assertCount(1, $withBilling->billingItems());
    }

    public function testRegistrationBillingTotalRounds(): void
    {
        $registration = new Registration(
            1,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))],
            Registration::SOURCE_CSV,
            new DateTimeImmutable(),
            99.999
        );

        self::assertSame(100.0, $registration->billingTotal());
    }

    public function testRegistrationDefaultSourceIsCsv(): void
    {
        $registration = new Registration(
            null,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))]
        );

        self::assertSame('csv', $registration->source());
    }

    public function testRegistrationManualSource(): void
    {
        $registration = new Registration(
            null,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))],
            Registration::SOURCE_MANUAL
        );

        self::assertSame('manual', $registration->source());
    }

    public function testRegistrationInvalidSourceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Registration(
            null,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))],
            'unknown'
        );
    }

    public function testRegistrationSourcePreservedByWithId(): void
    {
        $registration = new Registration(
            null,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))],
            Registration::SOURCE_MANUAL
        );

        $withId = $registration->withId(42);
        self::assertSame('manual', $withId->source());
    }

    public function testRegistrationSourcePreservedByWithBilling(): void
    {
        $registration = new Registration(
            1,
            1,
            '2-Bettzimmer',
            null,
            '',
            [new Participant('Max', new DateTimeImmutable('1985-01-01'))],
            Registration::SOURCE_MANUAL
        );

        $withBilling = $registration->withBilling(new DateTimeImmutable(), 100.0, []);
        self::assertSame('manual', $withBilling->source());
    }
}




