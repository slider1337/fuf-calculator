<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddEventAccessAndSettlement extends AbstractMigration
{
    public function change(): void
    {
        $this->table('events')
            ->addColumn('name', 'text')
            ->addColumn('description', 'text', ['default' => ''])
            ->addColumn('archived', 'boolean', ['default' => false])
            ->addColumn('revision', 'integer', ['default' => 1])
            ->create();
        $this->table('trips')
            ->addColumn('event_id', 'integer', ['null' => true])
            ->addColumn('closed_at', 'text', ['null' => true])
            ->addColumn('closed_by', 'integer', ['null' => true])
            ->addColumn('settlement_snapshot', 'text', ['null' => true])
            ->addColumn('calculation_snapshot', 'text', ['null' => true])
            ->addForeignKey('event_id', 'events', 'id', ['delete' => 'RESTRICT'])
            ->update();
        foreach (['event_access' => 'event_id', 'event_default_team' => 'event_id', 'trip_access' => 'trip_id'] as $table => $target) {
            $this->table($table)
                ->addColumn($target, 'integer')
                ->addColumn('user_id', 'integer')
                ->addColumn('capability', 'text')
                ->addForeignKey($target, $target === 'trip_id' ? 'trips' : 'events', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
                ->addIndex([$target, 'user_id', 'capability'], ['unique' => true])
                ->create();
        }
        $this->table('audit_events')
            ->addColumn('actor_id', 'integer')
            ->addColumn('action', 'text')
            ->addColumn('target_type', 'text')
            ->addColumn('target_id', 'integer')
            ->addColumn('reason', 'text')
            ->addColumn('created_at', 'text')
            ->addColumn('correlation_id', 'text')
            ->create();
        // Freeze the eligible legacy population, never auto-grant it.
        $this->table('legacy_access_candidates')
            ->addColumn('trip_id', 'integer')
            ->addColumn('user_id', 'integer')
            ->addIndex(['trip_id', 'user_id'], ['unique' => true])
            ->create();
        $this->execute('INSERT INTO legacy_access_candidates (trip_id, user_id) SELECT trips.id, users.id FROM trips CROSS JOIN users');
    }
}
