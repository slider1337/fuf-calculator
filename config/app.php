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
        if ($exception instanceof \App\Application\AccessException) {
            return \App\Infrastructure\Http\JsonResponder::write($app->getResponseFactory()->createResponse(), ['error' => $exception->error], $exception->status);
        }
        if ($exception instanceof \App\Application\ValidationException) {
            return \App\Infrastructure\Http\JsonResponder::write($app->getResponseFactory()->createResponse(), ['error' => 'validation_error', 'details' => $exception->errors()], 422);
        }
        if ($exception instanceof \App\Application\NotFoundException) {
            return \App\Infrastructure\Http\JsonResponder::write($app->getResponseFactory()->createResponse(), ['error' => 'not_found'], 404);
        }
        $response = $app->getResponseFactory()->createResponse(500);
        $response->getBody()->write((string)json_encode([
                                                            'error' => 'server_error',
                                                            'message' => $displayErrorDetails ? $exception->getMessage(
                                                            ) : 'Internal server error',
                                                        ], JSON_THROW_ON_ERROR)
        );

        return $response->withHeader('Content-Type', 'application/json');
    });

    $routes = require __DIR__ . '/routes.php';
    $routes($app);

    return $app;
};

