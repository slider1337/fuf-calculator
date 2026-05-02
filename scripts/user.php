<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? null;
$email = $argv[2] ?? null;

if (!in_array($command, ['set-password', 'make-admin', 'make-user', 'list'], true)) {
    printUsage();
    exit(1);
}

if ($command !== 'list' && $email === null) {
    printUsage();
    exit(1);
}

if ($email !== null) {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Ungültige E-Mail-Adresse: {$email}\n");
        exit(1);
    }
}

$containerFactory = require __DIR__ . '/../config/container.php';
$container = $containerFactory();

if ($command === 'set-password') {
    $password = readPassword('Neues Passwort (mind. 10 Zeichen): ');
    $confirm = readPassword('Passwort wiederholen: ');
    if ($password !== $confirm) {
        fwrite(STDERR, "Die Passwörter stimmen nicht überein.\n");
        exit(1);
    }

    $service = $container->get(\App\Application\AuthService::class);
    try {
        $user = $service->setPasswordDirect($email, $password);
    } catch (\App\Application\AuthException $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
    echo "Passwort für {$user->email()} gesetzt (Rolle: {$user->role()}).\n";
    exit(0);
}

if ($command === 'list') {
    $users = $container->get(\App\Application\UserService::class)->listUsers();
    if ($users === []) {
        echo "Keine Benutzer vorhanden.\n";
        exit(0);
    }
    foreach ($users as $user) {
        $marker = $user->hasPassword() ? '' : ' (kein Passwort gesetzt)';
        printf("[%s] %s%s\n", $user->role(), $user->email(), $marker);
    }
    exit(0);
}

// make-admin / make-user
$role = $command === 'make-admin' ? \App\Domain\User\User::ROLE_ADMIN : \App\Domain\User\User::ROLE_USER;
$service = $container->get(\App\Application\UserService::class);
try {
    $user = $service->setRole($email, $role);
} catch (\App\Application\NotFoundException | \App\Application\UserManagementException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
echo "Rolle von {$user->email()} auf {$user->role()} gesetzt.\n";

function printUsage(): void
{
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/user.php set-password <email>   Passwort (neu) setzen, legt User an falls nicht vorhanden\n");
    fwrite(STDERR, "  php scripts/user.php make-admin <email>     Benutzer zum Admin machen\n");
    fwrite(STDERR, "  php scripts/user.php make-user <email>      Admin auf normalen Benutzer zurückstufen\n");
    fwrite(STDERR, "  php scripts/user.php list                   Alle Benutzer auflisten\n");
}

function readPassword(string $prompt): string
{
    if (!stream_isatty(STDIN)) {
        fwrite(STDOUT, $prompt);
        $line = fgets(STDIN);
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    fwrite(STDOUT, $prompt);
    if (DIRECTORY_SEPARATOR === '\\') {
        $line = fgets(STDIN);
        fwrite(STDOUT, "\n");
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    system('stty -echo');
    $line = fgets(STDIN);
    system('stty echo');
    fwrite(STDOUT, "\n");
    return $line === false ? '' : rtrim($line, "\r\n");
}
