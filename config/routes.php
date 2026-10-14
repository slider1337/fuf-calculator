<?php

declare(strict_types=1);

use App\Infrastructure\Http\Auth\AuthMiddleware;
use App\Infrastructure\Http\Controller\ActualExpenseController;
use App\Infrastructure\Http\Controller\AuthController;
use App\Infrastructure\Http\Controller\RegistrationController;
use App\Infrastructure\Http\Controller\SettingsController;
use App\Infrastructure\Http\Controller\TripController;
use App\Infrastructure\Http\Controller\UserController;
use Slim\App;

return static function (App $app): void {
    $renderSpa = static function ($request, $response) {
        ob_start();
        require __DIR__ . '/../templates/index.html.php';
        $html = ob_get_clean();
        $response->getBody()->write((string) $html);
        return $response;
    };

    $app->get('/auth/easyverein', [\App\Infrastructure\Http\Controller\EasyVereinController::class, 'login']);
    $app->get('/auth/easyverein/callback', [\App\Infrastructure\Http\Controller\EasyVereinController::class, 'callback']);
    $app->get('/account/easyverein', [\App\Infrastructure\Http\Controller\EasyVereinController::class, 'account']);
    $app->post('/account/easyverein/unlink', [\App\Infrastructure\Http\Controller\EasyVereinController::class, 'unlink']);
    $app->post('/account/easyverein/link', [\App\Infrastructure\Http\Controller\EasyVereinController::class, 'link']);

    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->post('/logout', [AuthController::class, 'logout']);
    $app->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
    $app->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    $app->get('/set-password', [AuthController::class, 'showSetPassword']);
    $app->post('/set-password', [AuthController::class, 'setPassword']);

    $app->get('/', $renderSpa);
    $app->get('/trips/new', $renderSpa);
    $app->get('/events', $renderSpa);
    $app->get('/events/{id:[0-9]+}', $renderSpa);
    $app->get('/trips/{id:[0-9]+}', $renderSpa);

    $app->get('/api/auth/me', [AuthController::class, 'me']);
    $app->post('/api/auth/invite', [AuthController::class, 'invite']);

    $app->get('/api/users', [UserController::class, 'list']);
    $app->delete('/api/users/{id:[0-9]+}', [UserController::class, 'delete']);

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

    $workflow = \App\Infrastructure\Http\Controller\WorkflowController::class;
    $app->post('/api/trip-wizard', [$workflow, 'createTrip']);
    $app->get('/api/trips/{id:[0-9]+}/planning-transfer', [$workflow, 'planningStatus']);
    $app->post('/api/trips/{id:[0-9]+}/planning-transfer/preview', [$workflow, 'planningPreview']);
    $app->post('/api/trips/{id:[0-9]+}/planning-transfer/apply', [$workflow, 'planningApply']);
    $app->get('/api/trips/{id:[0-9]+}/term', [$workflow, 'term']);
    $app->put('/api/trips/{id:[0-9]+}/term/draft', [$workflow, 'draft']);
    $app->post('/api/trips/{id:[0-9]+}/term/review', [$workflow, 'review']);
    $app->post('/api/trips/{id:[0-9]+}/term/request', [$workflow, 'request']);
    $app->post('/api/trips/{id:[0-9]+}/term/link', [$workflow, 'link']);

    $member = \App\Infrastructure\Http\Controller\MemberController::class;
    $app->get('/integration/v1/events/{eventId:[0-9]+}/trip', [$member, 'resolve']);
    $app->get('/integration/v1/trips/{id:[0-9]+}/context', [$member, 'context']);
    $app->get('/integration/v1/trips/{id:[0-9]+}/bookings', [$member, 'list']);
    $app->get('/integration/v1/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}', [$member, 'get']);
    $app->post('/integration/v1/trips/{id:[0-9]+}/preview', [$member, 'preview']);
    $app->post('/integration/v1/trips/{id:[0-9]+}/bookings', [$member, 'create']);
    $app->put('/integration/v1/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}/answers', [$member, 'correct']);
    $app->post('/integration/v1/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}/cancel', [$member, 'cancel']);
    $app->get('/api/trips/{id:[0-9]+}/member-settings', [$member, 'settings']);
    $app->put('/api/trips/{id:[0-9]+}/member-settings', [$member, 'configure']);

    $participation = \App\Infrastructure\Http\Controller\ParticipationController::class;
    $app->get('/api/trips/{id:[0-9]+}/participation-sync', [$participation, 'read']);
    $app->post('/api/trips/{id:[0-9]+}/participation-sync/reconcile', [$participation, 'reconcile']);
    $app->post('/api/trips/{id:[0-9]+}/participation-sync/acknowledge', [$participation, 'acknowledge']);
    $app->post('/api/trips/{id:[0-9]+}/participation-sync/jobs/{jobId:[0-9]+}/retry', [$participation, 'retry']);
    $app->post('/api/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}/participations', [$participation, 'request']);

    $booking = \App\Infrastructure\Http\Controller\BookingController::class;
    $app->get('/api/trips/{id:[0-9]+}/bookings', [$booking, 'list']);
    $app->post('/api/trips/{id:[0-9]+}/bookings', [$booking, 'create']);
    $app->get('/api/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}', [$booking, 'get']);
    $app->put('/api/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}/answers', [$booking, 'correct']);
    $app->post('/api/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}/cancel', [$booking, 'cancel']);
    $app->put('/api/trips/{id:[0-9]+}/bookings/{bookingId:[0-9]+}/room', [$booking, 'room']);
    $app->get('/api/trips/{id:[0-9]+}/analytics', [$booking, 'analytics']);
    $app->get('/api/events/{id:[0-9]+}/analytics', [$booking, 'eventAnalytics']);

    $forms = \App\Infrastructure\Http\Controller\FormController::class;
    $app->get('/api/events/{eventId:[0-9]+}/forms', [$forms, 'list']);
    $app->post('/api/events/{eventId:[0-9]+}/forms', [$forms, 'create']);
    $app->get('/api/events/{eventId:[0-9]+}/forms/compare', [$forms, 'compare']);
    $app->get('/api/events/{eventId:[0-9]+}/forms/{versionId:[0-9]+}', [$forms, 'get']);
    $app->put('/api/events/{eventId:[0-9]+}/forms/{versionId:[0-9]+}', [$forms, 'save']);
    $app->post('/api/events/{eventId:[0-9]+}/forms/{versionId:[0-9]+}/preview', [$forms, 'preview']);
    $app->post('/api/events/{eventId:[0-9]+}/forms/{versionId:[0-9]+}/publish', [$forms, 'publish']);
    $app->post('/api/events/{eventId:[0-9]+}/forms/{versionId:[0-9]+}/archive', [$forms, 'archive']);
    $app->get('/api/trips/{id:[0-9]+}/form', [$forms, 'binding']);
    $app->post('/api/trips/{id:[0-9]+}/form/preview-switch', [$forms, 'switchPreview']);
    $app->put('/api/trips/{id:[0-9]+}/form', [$forms, 'switchVersion']);

    $eventController = \App\Infrastructure\Http\Controller\EventController::class;
    $app->get('/api/events/{id:[0-9]+}/people', [$eventController, 'people']);
    $app->get('/api/trips/{id:[0-9]+}/people', [$eventController, 'people']);
    $app->get('/api/events', [$eventController, 'list']);
    $app->post('/api/events', [$eventController, 'create']);
    $app->get('/api/events/{id:[0-9]+}', [$eventController, 'get']);
    $app->put('/api/events/{id:[0-9]+}', [$eventController, 'update']);
    foreach (['/api/events/{id:[0-9]+}/access', '/api/events/{id:[0-9]+}/default-team', '/api/trips/{id:[0-9]+}/team'] as $teamPath) {
        $app->get($teamPath, [$eventController, 'team']);
        $app->put($teamPath . '/{userId:[0-9]+}', [$eventController, 'grant']);
    }
    $app->put('/api/trips/{id:[0-9]+}/event', [$eventController, 'assign']);
    $app->put('/api/trips/{id:[0-9]+}/archive', [$eventController, 'archive']);
    $app->post('/api/trips/{id:[0-9]+}/close', [$eventController, 'close']);
    $app->post('/api/trips/{id:[0-9]+}/reopen', [$eventController, 'reopen']);
    $app->post('/api/trips/{id:[0-9]+}/duplicate', [$eventController, 'duplicate']);
    $app->get('/api/admin/legacy-access', [$eventController, 'legacyPreview']);
    $app->post('/api/admin/legacy-access', [$eventController, 'legacyImport']);

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

    $app->add(AuthMiddleware::class);
};
