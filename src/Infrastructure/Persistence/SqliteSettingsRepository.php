<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\SettingsRepositoryInterface;
use App\Domain\Settings\Settings;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use PDO;

final class SqliteSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function get(): Settings
    {
        $row = $this->pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
        if ($row === false) {
            $default = new Settings(
                new Percentage(10.00),
                new Percentage(5.00),
                DistributionMethod::PER_PERSON,
                0.00,
                0
            );
            $this->save($default);
            return $default;
        }

        return new Settings(
            new Percentage((float) $row['default_markup_percent']),
            new Percentage((float) $row['default_club_fee_percent']),
            DistributionMethod::from((string) $row['default_distribution_method']),
            (float) $row['default_spa_tax_per_person'],
            (int) $row['default_spa_tax_age_threshold']
        );
    }

    public function save(Settings $settings): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (
                id,
                default_markup_percent,
                default_club_fee_percent,
                default_distribution_method,
                default_spa_tax_per_person,
                default_spa_tax_age_threshold
            ) VALUES (1, :markup, :club, :method, :spaTax, :age)
            ON CONFLICT(id) DO UPDATE SET
                default_markup_percent = excluded.default_markup_percent,
                default_club_fee_percent = excluded.default_club_fee_percent,
                default_distribution_method = excluded.default_distribution_method,
                default_spa_tax_per_person = excluded.default_spa_tax_per_person,
                default_spa_tax_age_threshold = excluded.default_spa_tax_age_threshold'
        );

        $statement->execute([
            ':markup' => $settings->defaultMarkupPercent()->value(),
            ':club' => $settings->defaultClubFeePercent()->value(),
            ':method' => $settings->defaultDistributionMethod()->value,
            ':spaTax' => $settings->defaultSpaTaxPerPerson(),
            ':age' => $settings->defaultSpaTaxAgeThreshold(),
        ]);
    }
}

