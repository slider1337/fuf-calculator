<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\Port\SettingsRepositoryInterface;
use App\Application\SettingsService;
use App\Application\ValidationException;
use App\Domain\Settings\Settings;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use PHPUnit\Framework\TestCase;

final class SettingsServiceTest extends TestCase
{
    private SettingsService $service;

    protected function setUp(): void
    {
        $repository = new InMemorySettingsRepository();
        $this->service = new SettingsService($repository);
    }

    public function testGetSettingsReturnsDefault(): void
    {
        $settings = $this->service->getSettings();

        self::assertSame(10.0, $settings->defaultMarkupPercent()->value());
        self::assertSame(5.0, $settings->defaultClubFeePercent()->value());
    }

    public function testUpdateFromArrayUpdatesSettings(): void
    {
        $settings = $this->service->updateFromArray([
            'defaultMarkupPercent' => 15.0,
            'defaultClubFeePercent' => 8.0,
            'defaultDistributionMethod' => 'PER_CATEGORY_UNITS',
            'defaultSpaTaxPerPerson' => 3.5,
            'defaultSpaTaxAgeThreshold' => 16,
        ]);

        self::assertSame(15.0, $settings->defaultMarkupPercent()->value());
        self::assertSame(8.0, $settings->defaultClubFeePercent()->value());
        self::assertSame(DistributionMethod::PER_CATEGORY_UNITS, $settings->defaultDistributionMethod());
        self::assertSame(3.5, $settings->defaultSpaTaxPerPerson());
        self::assertSame(16, $settings->defaultSpaTaxAgeThreshold());
    }

    public function testUpdateFromArrayPersistsToRepository(): void
    {
        $this->service->updateFromArray([
            'defaultMarkupPercent' => 20.0,
            'defaultClubFeePercent' => 6.0,
            'defaultDistributionMethod' => 'PER_PERSON',
            'defaultSpaTaxPerPerson' => 2.0,
            'defaultSpaTaxAgeThreshold' => 18,
        ]);

        $retrieved = $this->service->getSettings();
        self::assertSame(20.0, $retrieved->defaultMarkupPercent()->value());
    }

    public function testUpdateFromArrayWithMissingFieldsThrowsValidation(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->updateFromArray(['defaultMarkupPercent' => 10.0]);
    }

    public function testUpdateFromArrayWithAllFieldsMissingThrowsValidation(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->updateFromArray([]);
    }

    public function testUpdateFromArrayWithInvalidDistributionMethodThrowsValidation(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->updateFromArray([
            'defaultMarkupPercent' => 10.0,
            'defaultClubFeePercent' => 5.0,
            'defaultDistributionMethod' => 'INVALID',
            'defaultSpaTaxPerPerson' => 2.0,
            'defaultSpaTaxAgeThreshold' => 18,
        ]);
    }

    public function testUpdateFromArrayWithNegativePercentageThrowsValidation(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->updateFromArray([
            'defaultMarkupPercent' => -5.0,
            'defaultClubFeePercent' => 5.0,
            'defaultDistributionMethod' => 'PER_PERSON',
            'defaultSpaTaxPerPerson' => 2.0,
            'defaultSpaTaxAgeThreshold' => 18,
        ]);
    }
}

final class InMemorySettingsRepository implements SettingsRepositoryInterface
{
    private ?Settings $settings = null;

    public function get(): Settings
    {
        return $this->settings ?? new Settings(
            new Percentage(10.0),
            new Percentage(5.0),
            DistributionMethod::PER_PERSON,
            0.0,
            0
        );
    }

    public function save(Settings $settings): void
    {
        $this->settings = $settings;
    }
}

