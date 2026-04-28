<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controller\ActualExpenseController;
use App\Infrastructure\Http\Controller\RegistrationController;
use App\Infrastructure\Http\Controller\SettingsController;
use App\Infrastructure\Http\Controller\TripController;
use Slim\App;

return static function (App $app): void {
    $renderSpa = static function ($request, $response) {
        ob_start();
        require __DIR__ . '/../templates/index.html.php';
        $html = ob_get_clean();
        $response->getBody()->write((string) $html);
        return $response;
    };

    $app->get('/', $renderSpa);
    $app->get('/trips/new', $renderSpa);
    $app->get('/trips/{id:[0-9]+}', $renderSpa);

    $app->get('/api/settings', [SettingsController::class, 'get']);
    $app->put('/api/settings', [SettingsController::class, 'update']);

    $app->get('/api/trips', [TripController::class, 'list']);
    $app->post('/api/trips', [TripController::class, 'create']);
    $app->put('/api/trips/{id}', [TripController::class, 'update']);
    $app->get('/api/trips/{id}', [TripController::class, 'get']);
    $app->post('/api/trips/{id}/calculate', [TripController::class, 'calculate']);

    $app->get('/api/trips/{id}/registrations', [RegistrationController::class, 'list']);
    $app->post('/api/trips/{id}/registrations', [RegistrationController::class, 'create']);
    $app->post('/api/trips/{id}/registrations/import', [RegistrationController::class, 'import']);
    $app->post('/api/trips/{id}/registrations/recalculate', [RegistrationController::class, 'recalculate']);
    $app->delete('/api/trips/{id}/registrations/{registrationId}', [RegistrationController::class, 'deleteSingle']);
    $app->delete('/api/trips/{id}/registrations', [RegistrationController::class, 'delete']);

    $app->get('/api/trips/{id}/actual-expenses', [ActualExpenseController::class, 'list']);
    $app->post('/api/trips/{id}/actual-expenses', [ActualExpenseController::class, 'create']);
    $app->put('/api/trips/{id}/actual-expenses/{expenseId}', [ActualExpenseController::class, 'update']);
    $app->delete('/api/trips/{id}/actual-expenses/{expenseId}', [ActualExpenseController::class, 'delete']);
    $app->get('/api/trips/{id}/settlement', [ActualExpenseController::class, 'settlement']);

    $app->get('/docs', static function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/docs/index.html');
        $response->getBody()->write((string) $html);
        return $response;
    });

    $app->get('/openapi.yaml', static function ($request, $response) {
        $content = file_get_contents(__DIR__ . '/../docs/openapi.yaml');
        $response->getBody()->write((string) $content);
        return $response->withHeader('Content-Type', 'application/yaml');
    });
};


