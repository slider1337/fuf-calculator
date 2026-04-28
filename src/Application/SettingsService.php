<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\SettingsRepositoryInterface;
use App\Domain\Settings\Settings;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use Throwable;

final readonly class SettingsService
{
    public function __construct(private SettingsRepositoryInterface $repository)
    {
    }

    public function getSettings(): Settings
    {
        return $this->repository->get();
    }

    public function updateFromArray(array $payload): Settings
    {
        $errors = [];
        foreach (['defaultMarkupPercent', 'defaultClubFeePercent', 'defaultDistributionMethod', 'defaultSpaTaxPerPerson', 'defaultSpaTaxAgeThreshold'] as $field) {
            if (!array_key_exists($field, $payload)) {
                $errors[$field] = 'required';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $settings = new Settings(
                new Percentage((float) $payload['defaultMarkupPercent']),
                new Percentage((float) $payload['defaultClubFeePercent']),
                DistributionMethod::from((string) $payload['defaultDistributionMethod']),
                (float) $payload['defaultSpaTaxPerPerson'],
                (int) $payload['defaultSpaTaxAgeThreshold']
            );
        } catch (Throwable $exception) {
            throw new ValidationException(['payload' => $exception->getMessage()]);
        }

        $this->repository->save($settings);

        return $settings;
    }
}


