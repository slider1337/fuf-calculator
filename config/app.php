<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use Psr\Container\ContainerInterface;
use Slim\App;

return static function (?ContainerInterface $container = null): App {
    if ($container === null) {
        $containerFactory = require __DIR__ . '/container.php';
        $container = $containerFactory();
    }

    $app = Bridge::create($container);
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $displayErrors = (bool) (getenv('APP_DEBUG') ?: false);
    $errorMiddleware = $app->addErrorMiddleware($displayErrors, false, false);
    $errorMiddleware->setDefaultErrorHandler(static function ($request, Throwable $exception, bool $displayErrorDetails) use ($app) {
        $response = $app->getResponseFactory()->createResponse(500);
        $response->getBody()->write((string) json_encode([
            'error' => 'server_error',
            'message' => $displayErrorDetails ? $exception->getMessage() : 'Internal server error',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $routes = require __DIR__ . '/routes.php';
    $routes($app);

    return $app;
};

