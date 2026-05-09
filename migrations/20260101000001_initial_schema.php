<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    public function change(): void
    {
        $this->table('settings', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['null' => false])
            ->addColumn('default_markup_percent', 'float', ['null' => false])
            ->addColumn('default_club_fee_percent', 'float', ['null' => false])
            ->addColumn('default_distribution_method', 'text', ['null' => false])
            ->addColumn('default_spa_tax_per_person', 'float', ['null' => false])
            ->addColumn('default_spa_tax_age_threshold', 'integer', ['null' => false])
            ->create();

        $this->execute('CREATE TRIGGER settings_singleton_check BEFORE INSERT ON settings BEGIN SELECT RAISE(ABORT, \'settings.id must be 1\') WHERE NEW.id <> 1; END;');

        $this->table('trips')
            ->addColumn('name', 'text', ['null' => false])
            ->addColumn('start_date', 'text', ['null' => false])
            ->addColumn('markup_percent', 'float', ['null' => false])
            ->addColumn('club_fee_percent', 'float', ['null' => false])
            ->addColumn('distribution_method', 'text', ['null' => false])
            ->addColumn('spa_tax_per_person', 'float', ['null' => false])
            ->addColumn('spa_tax_age_threshold', 'integer', ['null' => false])
            ->addColumn('planned_total_costs', 'float', ['null' => false])
            ->addColumn('planned_total_revenue', 'float', ['null' => false])
            ->create();

        $this->table('trip_bookings')
            ->addColumn('trip_id', 'integer', ['null' => false])
            ->addColumn('category_type', 'text', ['null' => false])
            ->addColumn('participant_count', 'integer', ['null' => false])
            ->addColumn('base_price_per_person', 'float', ['null' => false])
            ->addForeignKey('trip_id', 'trips', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('trip_group_expenses')
            ->addColumn('trip_id', 'integer', ['null' => false])
            ->addColumn('label', 'text', ['null' => false])
            ->addColumn('amount', 'float', ['null' => false])
            ->addForeignKey('trip_id', 'trips', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('registrations')
            ->addColumn('trip_id', 'integer', ['null' => false])
            ->addColumn('room_category', 'text', ['null' => false])
            ->addColumn('received_at', 'text', ['null' => true])
            ->addColumn('comment', 'text', ['null' => false, 'default' => ''])
            ->addColumn('billing_calculated_at', 'text', ['null' => true])
            ->addColumn('billing_total', 'float', ['null' => true])
            ->addForeignKey('trip_id', 'trips', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('registration_participants')
            ->addColumn('registration_id', 'integer', ['null' => false])
            ->addColumn('name', 'text', ['null' => false])
            ->addColumn('birth_date', 'text', ['null' => false])
            ->addForeignKey('registration_id', 'registrations', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('registration_billing_items')
            ->addColumn('registration_id', 'integer', ['null' => false])
            ->addColumn('participant_name', 'text', ['null' => false])
            ->addColumn('category_type', 'text', ['null' => false])
            ->addColumn('price', 'float', ['null' => false])
            ->addForeignKey('registration_id', 'registrations', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('actual_expenses')
            ->addColumn('trip_id', 'integer', ['null' => false])
            ->addColumn('label', 'text', ['null' => false])
            ->addColumn('amount', 'float', ['null' => false])
            ->addForeignKey('trip_id', 'trips', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
