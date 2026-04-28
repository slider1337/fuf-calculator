<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\SettingsService;
use App\Application\ValidationException;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SettingsController
{
    public function __construct(private SettingsService $service)
    {
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $settings = $this->service->getSettings();

        return JsonResponder::write($response, [
            'defaultMarkupPercent' => $settings->defaultMarkupPercent()->value(),
            'defaultClubFeePercent' => $settings->defaultClubFeePercent()->value(),
            'defaultDistributionMethod' => $settings->defaultDistributionMethod()->value,
            'defaultSpaTaxPerPerson' => $settings->defaultSpaTaxPerPerson(),
            'defaultSpaTaxAgeThreshold' => $settings->defaultSpaTaxAgeThreshold(),
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();

        try {
            $settings = $this->service->updateFromArray($payload);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        }

        return JsonResponder::write($response, [
            'defaultMarkupPercent' => $settings->defaultMarkupPercent()->value(),
            'defaultClubFeePercent' => $settings->defaultClubFeePercent()->value(),
            'defaultDistributionMethod' => $settings->defaultDistributionMethod()->value,
            'defaultSpaTaxPerPerson' => $settings->defaultSpaTaxPerPerson(),
            'defaultSpaTaxAgeThreshold' => $settings->defaultSpaTaxAgeThreshold(),
        ]);
    }
}

