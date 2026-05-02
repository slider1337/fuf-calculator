<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

abstract class ApiTestCase extends TestCase
{
    private string $dbPath;

    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = sys_get_temp_dir() . '/fuf-test-' . uniqid('', true) . '.sqlite';
        putenv('DB_PATH=' . $this->dbPath);

        $factory = require __DIR__ . '/../../config/app.php';
        $this->app = $factory();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = 1;
        $_SESSION['user_email'] = 'test@example.com';
        $_SESSION['user_role'] = 'admin';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }

        putenv('DB_PATH');
        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    protected function dispatch(string $method, string $path, ?array $jsonPayload = null): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, $path);

        if ($jsonPayload !== null) {
            $json = json_encode($jsonPayload, JSON_THROW_ON_ERROR);
            $stream = new StreamFactory()->createStream($json);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream);
        }

        return $this->app->handle($request);
    }

    protected function validTripPayload(): array
    {
        return [
            'name' => 'API Test Reise',
            'startDate' => '2026-12-20',
            'markupPercent' => 10.0,
            'clubFeePercent' => 5.0,
            'distributionMethod' => 'PER_PERSON',
            'spaTaxPerPerson' => 2.0,
            'spaTaxAgeThreshold' => 18,
            'bookings' => [
                ['categoryType' => 'ADULT_DOUBLE', 'count' => 3, 'basePricePerPerson' => 100.0],
                ['categoryType' => 'ADULT_MULTI', 'count' => 2, 'basePricePerPerson' => 90.0],
                ['categoryType' => 'CHILD', 'count' => 1, 'basePricePerPerson' => 60.0],
            ],
            'groupExpenses' => [
                ['label' => 'Snacks', 'amount' => 30.0],
            ],
        ];
    }
}

