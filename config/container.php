<?php

declare(strict_types=1);

use App\Application\ActualExpenseService;
use App\Application\Port\ActualExpenseRepositoryInterface;
use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\Port\SettingsRepositoryInterface;
use App\Application\Port\TripRepositoryInterface;
use App\Application\RegistrationService;
use App\Application\SettingsService;
use App\Application\TripService;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Service\RegistrationBillingService;
use App\Infrastructure\Http\Controller\ActualExpenseController;
use App\Infrastructure\Http\Controller\RegistrationController;
use App\Infrastructure\Http\Controller\SettingsController;
use App\Infrastructure\Http\Controller\TripController;
use App\Infrastructure\Persistence\SqliteActualExpenseRepository;
use App\Infrastructure\Persistence\SqliteConnection;
use App\Infrastructure\Persistence\SqliteRegistrationRepository;
use App\Infrastructure\Persistence\SqliteSchema;
use App\Infrastructure\Persistence\SqliteSettingsRepository;
use App\Infrastructure\Persistence\SqliteTripRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use function DI\autowire;

return static function (): ContainerInterface {
    $builder = new ContainerBuilder();

    $builder->addDefinitions([
        \PDO::class => static function (): \PDO {
            $dbPath = getenv('DB_PATH') ?: (__DIR__ . '/../database/fuf.sqlite');
            $pdo = SqliteConnection::create($dbPath);
            SqliteSchema::ensure($pdo, __DIR__ . '/../database/schema.sql');
            return $pdo;
        },
        SettingsRepositoryInterface::class => autowire(SqliteSettingsRepository::class),
        TripRepositoryInterface::class => autowire(SqliteTripRepository::class),
        RegistrationRepositoryInterface::class => autowire(SqliteRegistrationRepository::class),
        ActualExpenseRepositoryInterface::class => autowire(SqliteActualExpenseRepository::class),
        SettingsService::class => autowire(),
        TripService::class => autowire(),
        RegistrationService::class => autowire(),
        ActualExpenseService::class => autowire(),
        PriceCalculatorService::class => autowire(),
        RegistrationBillingService::class => autowire(),
        SettingsController::class => autowire(),
        TripController::class => autowire(),
        RegistrationController::class => autowire(),
        ActualExpenseController::class => autowire(),
    ]);

    return $builder->build();
};



