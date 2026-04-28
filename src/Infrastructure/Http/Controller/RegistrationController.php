<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\NotFoundException;
use App\Application\RegistrationService;
use App\Application\ValidationException;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegistrationController
{
    public function __construct(private RegistrationService $service)
    {
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();
        $csvFile = $uploadedFiles['csv_file'] ?? null;

        if ($csvFile === null || $csvFile->getError() !== UPLOAD_ERR_OK) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => ['csv_file' => 'file_required'],
            ], 422);
        }

        $csvContent = (string) $csvFile->getStream();

        try {
            $registrations = $this->service->importCsv((int) $id, $csvContent);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->registrationsToArray($registrations), 201);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        try {
            $registrations = $this->service->addManualRegistration((int) $id, $body);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->registrationsToArray($registrations), 201);
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $registrations = $this->service->getRegistrations((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->registrationsToArray($registrations));
    }

    public function recalculate(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $registrations = $this->service->recalculateBillings((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->registrationsToArray($registrations));
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $this->service->deleteRegistrations((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, ['status' => 'deleted']);
    }

    public function deleteSingle(ServerRequestInterface $request, ResponseInterface $response, string $id, string $registrationId): ResponseInterface
    {
        try {
            $registrations = $this->service->deleteRegistration((int) $id, (int) $registrationId);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->registrationsToArray($registrations));
    }

    private function registrationsToArray(array $registrations): array
    {
        return array_map(static function ($reg) {
            return [
                'id' => $reg->id(),
                'tripId' => $reg->tripId(),
                'roomCategory' => $reg->roomCategory(),
                'receivedAt' => $reg->receivedAt(),
                'comment' => $reg->comment(),
                'source' => $reg->source(),
                'participants' => array_map(static fn ($p) => [
                    'name' => $p->name(),
                    'birthDate' => $p->birthDate()->format('Y-m-d'),
                ], $reg->participants()),
                'billingCalculatedAt' => $reg->billingCalculatedAt()?->format('Y-m-d H:i:s'),
                'billingTotal' => $reg->billingTotal(),
                'billingItems' => array_map(static fn ($item) => [
                    'participantName' => $item->participantName(),
                    'categoryType' => $item->categoryType(),
                    'price' => $item->price(),
                ], $reg->billingItems()),
            ];
        }, $registrations);
    }
}



