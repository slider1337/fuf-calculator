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

    /** @param array<array-key,mixed> $payload */
    public function updateFromArray(array $payload): Settings
    {
        $errors = [];
        foreach (['defaultMarkupPercent', 'defaultClubFeePercent', 'defaultDistributionMethod', 'defaultSpaTaxPerPerson', 'defaultSpaTaxAgeThreshold', 'defaultAdultAgeThreshold'] as $field) {
            if (!array_key_exists($field, $payload)) {
                $errors[$field] = 'required';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $settings = new Settings(
                new Percentage((float) Input::scalar($payload['defaultMarkupPercent'])),
                new Percentage((float) Input::scalar($payload['defaultClubFeePercent'])),
                DistributionMethod::from((string) Input::scalar($payload['defaultDistributionMethod'])),
                (float) Input::scalar($payload['defaultSpaTaxPerPerson']),
                (int) Input::scalar($payload['defaultSpaTaxAgeThreshold']),
                (int) Input::scalar($payload['defaultAdultAgeThreshold'])
            );
        } catch (Throwable $exception) {
            throw new ValidationException(['payload' => $exception->getMessage()]);
        }

        $this->repository->save($settings);

        return $settings;
    }
}


