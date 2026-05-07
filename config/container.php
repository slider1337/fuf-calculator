<?php

declare(strict_types=1);

use App\Application\ActualExpenseService;
use App\Application\AuthService;
use App\Application\Port\ActualExpenseRepositoryInterface;
use App\Application\Port\AuthTokenRepositoryInterface;
use App\Application\Port\MailerInterface;
use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\Port\SettingsRepositoryInterface;
use App\Application\Port\TripRepositoryInterface;
use App\Application\Port\UserRepositoryInterface;
use App\Application\RegistrationService;
use App\Application\SettingsService;
use App\Application\TripService;
use App\Application\UserService;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Service\RegistrationBillingService;
use App\Infrastructure\Http\Auth\AuthMiddleware;
use App\Infrastructure\Http\Controller\ActualExpenseController;
use App\Infrastructure\Http\Controller\AuthController;
use App\Infrastructure\Http\Controller\RegistrationController;
use App\Infrastructure\Http\Controller\SettingsController;
use App\Infrastructure\Http\Controller\TripController;
use App\Infrastructure\Http\Controller\UserController;
use App\Infrastructure\Mail\SymfonyMailer;
use App\Infrastructure\Persistence\SqliteActualExpenseRepository;
use App\Infrastructure\Persistence\SqliteAuthTokenRepository;
use App\Infrastructure\Persistence\SqliteConnection;
use App\Infrastructure\Persistence\SqliteRegistrationRepository;
use App\Infrastructure\Persistence\SqliteSettingsRepository;
use App\Infrastructure\Persistence\SqliteTripRepository;
use App\Infrastructure\Persistence\SqliteUserRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use function DI\autowire;
use function DI\factory;

return static function (): ContainerInterface {
    $builder = new ContainerBuilder();

    $builder->addDefinitions([
        PDO::class => static function (): PDO {
            $dbPath = getenv('DB_PATH') ?: (__DIR__ . '/../database/fuf.sqlite');
            return SqliteConnection::create($dbPath);
        },
        SettingsRepositoryInterface::class => autowire(SqliteSettingsRepository::class),
        TripRepositoryInterface::class => autowire(SqliteTripRepository::class),
        RegistrationRepositoryInterface::class => autowire(SqliteRegistrationRepository::class),
        ActualExpenseRepositoryInterface::class => autowire(SqliteActualExpenseRepository::class),
        UserRepositoryInterface::class => autowire(SqliteUserRepository::class),
        AuthTokenRepositoryInterface::class => autowire(SqliteAuthTokenRepository::class),
        MailerInterface::class => factory(static function (): MailerInterface {
            $dsn = getenv('MAILER_DSN') ?: 'null://null';
            $from = getenv('MAIL_FROM') ?: 'noreply@example.com';
            return new SymfonyMailer($dsn, $from);
        }),
        AuthService::class => factory(static function (ContainerInterface $c): AuthService {
            $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
            return new AuthService(
                $c->get(UserRepositoryInterface::class),
                $c->get(AuthTokenRepositoryInterface::class),
                $c->get(MailerInterface::class),
                $appUrl,
            );
        }),
        SettingsService::class => autowire(),
        TripService::class => autowire(),
        RegistrationService::class => autowire(),
        ActualExpenseService::class => autowire(),
        UserService::class => autowire(),
        PriceCalculatorService::class => autowire(),
        RegistrationBillingService::class => autowire(),
        SettingsController::class => autowire(),
        TripController::class => autowire(),
        RegistrationController::class => autowire(),
        ActualExpenseController::class => autowire(),
        AuthController::class => autowire(),
        UserController::class => autowire(),
        AuthMiddleware::class => autowire(),
    ]);

    return $builder->build();
};
