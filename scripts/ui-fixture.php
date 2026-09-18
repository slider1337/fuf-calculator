<?php

/**
 * Befuellt eine Wegwerf-Datenbank mit Demodaten fuer UI-Screenshots.
 *
 * Aufruf:
 *   DB_PATH=/tmp/fuf-ui.sqlite php vendor/bin/phinx migrate
 *   DB_PATH=/tmp/fuf-ui.sqlite php scripts/ui-fixture.php
 *
 * Schutz: Der Pfad darf nicht auf database/fuf.sqlite zeigen. Diese Datei ist
 * ein Entwicklungswerkzeug und nicht Teil der Anwendung.
 */

declare(strict_types=1);

use App\Application\RegistrationService;
use App\Application\TripService;

require __DIR__ . '/../vendor/autoload.php';

$dbPath = getenv('DB_PATH');
if ($dbPath === false || $dbPath === '') {
    fwrite(STDERR, "DB_PATH muss gesetzt sein.\n");
    exit(1);
}

$real = realpath($dbPath) ?: $dbPath;
if (str_contains($real, 'database/fuf.sqlite')) {
    fwrite(STDERR, "Abbruch: DB_PATH zeigt auf die Entwicklungsdatenbank.\n");
    exit(1);
}

$demoEmail = getenv('UI_EMAIL') ?: 'demo@fuf-erding.de';
$demoPassword = getenv('UI_PASSWORD') ?: 'demo12345';

$containerFactory = require __DIR__ . '/../config/container.php';
$container = $containerFactory();

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

// --- Benutzer -----------------------------------------------------------
$pdo->prepare('DELETE FROM users WHERE email = :email')->execute([':email' => $demoEmail]);
$pdo->prepare(
    'INSERT INTO users (email, password_hash, role, created_at) VALUES (:email, :hash, :role, :created)'
)->execute([
    ':email' => $demoEmail,
    ':hash' => password_hash($demoPassword, PASSWORD_DEFAULT),
    ':role' => 'admin',
    ':created' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
]);

// --- Reisen -------------------------------------------------------------
/** @var TripService $trips */
$trips = $container->get(TripService::class);
/** @var RegistrationService $registrations */
$registrations = $container->get(RegistrationService::class);

$winter = $trips->createFromArray([
    'name' => 'Winterfreizeit Lungau 2027',
    'startDate' => '2027-02-10',
    'endDate' => '2027-02-14',
    'markupPercent' => 10.0,
    'clubFeePercent' => 5.0,
    'distributionMethod' => 'PER_PERSON',
    'spaTaxPerPerson' => 2.85,
    'spaTaxAgeThreshold' => 15,
    'adultAgeThreshold' => 16,
    'spaTaxCount' => 48,
    'bookings' => [
        ['categoryType' => 'ADULT_DOUBLE', 'count' => 24, 'basePricePerPerson' => 58.0],
        ['categoryType' => 'ADULT_MULTI', 'count' => 20, 'basePricePerPerson' => 49.0],
        ['categoryType' => 'CHILD', 'count' => 12, 'basePricePerPerson' => 32.0],
    ],
    'groupExpenses' => [
        ['label' => 'Busanreise', 'amount' => 2400.0],
    ],
]);

$zelten = $trips->createFromArray([
    'name' => 'Familienzelten 2026',
    'startDate' => '2026-07-10',
    'endDate' => '2026-07-12',
    'markupPercent' => 10.0,
    'clubFeePercent' => 5.0,
    'distributionMethod' => 'PER_CATEGORY_UNITS',
    'spaTaxPerPerson' => 1.5,
    'spaTaxAgeThreshold' => 18,
    'adultAgeThreshold' => 16,
    'spaTaxCount' => 30,
    'bookings' => [
        ['categoryType' => 'ADULT_DOUBLE', 'count' => 18, 'basePricePerPerson' => 22.0],
        ['categoryType' => 'ADULT_MULTI', 'count' => 12, 'basePricePerPerson' => 18.0],
        ['categoryType' => 'CHILD', 'count' => 30, 'basePricePerPerson' => 9.0],
    ],
    'groupExpenses' => [
        ['label' => 'Verpflegung', 'amount' => 780.0],
    ],
]);

$chiemsee = $trips->createFromArray([
    'name' => 'Sommerfreizeit Chiemsee 2025',
    'startDate' => '2025-08-01',
    'endDate' => '2025-08-06',
    'markupPercent' => 8.0,
    'clubFeePercent' => 5.0,
    'distributionMethod' => 'PER_PERSON',
    'spaTaxPerPerson' => 2.0,
    'spaTaxAgeThreshold' => 16,
    'adultAgeThreshold' => 16,
    'spaTaxCount' => 54,
    'bookings' => [
        ['categoryType' => 'ADULT_DOUBLE', 'count' => 30, 'basePricePerPerson' => 44.0],
        ['categoryType' => 'ADULT_MULTI', 'count' => 14, 'basePricePerPerson' => 38.0],
        ['categoryType' => 'CHILD', 'count' => 10, 'basePricePerPerson' => 25.0],
    ],
    'groupExpenses' => [
        ['label' => 'Ausflug Herreninsel', 'amount' => 640.0],
    ],
]);

// --- Anmeldungen fuer die Winterfreizeit --------------------------------
$demoRegistrations = [
    ['2-Bettzimmer', 'Wohnt neben Familie Berger', [
        ['name' => 'Andreas Huber', 'birthDate' => '1979-04-12'],
        ['name' => 'Sabine Huber', 'birthDate' => '1981-09-30'],
    ]],
    ['4-Bettzimmer', 'Bitte unteres Stockwerk', [
        ['name' => 'Martin Berger', 'birthDate' => '1984-01-22'],
        ['name' => 'Julia Berger', 'birthDate' => '1986-06-05'],
        ['name' => 'Lena Berger', 'birthDate' => '2015-03-18'],
        ['name' => 'Tim Berger', 'birthDate' => '2018-11-02'],
    ]],
    ['3-Bettzimmer', '', [
        ['name' => 'Christina Moser', 'birthDate' => '1975-12-08'],
        ['name' => 'Paul Moser', 'birthDate' => '2011-07-21'],
        ['name' => 'Emma Moser', 'birthDate' => '2013-02-14'],
    ]],
];

foreach ($demoRegistrations as [$roomCategory, $comment, $participants]) {
    $registrations->addManualRegistration($winter->id(), [
        'roomCategory' => $roomCategory,
        'comment' => $comment,
        'participants' => $participants,
    ]);
}

printf(
    "Fixture fertig: %s / %s, Reisen #%d #%d #%d, %d Anmeldungen\n",
    $demoEmail,
    $demoPassword,
    $winter->id(),
    $zelten->id(),
    $chiemsee->id(),
    count($demoRegistrations)
);
