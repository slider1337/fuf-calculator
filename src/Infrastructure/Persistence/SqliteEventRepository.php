<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\AccessException;
use App\Application\Port\EventRepositoryInterface;
use App\Domain\Event\Event;
use PDO;
final readonly class SqliteEventRepository implements EventRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    /** @return list<\App\Domain\Event\Event> */
    public function all(): array
    {
        return array_map($this->hydrate(...), StoredRow::fetchAll($this->pdo->query('SELECT * FROM events ORDER BY name, id')));
    }
    public function get(int $id): ?Event
    {
        $s = $this->pdo->prepare('SELECT * FROM events WHERE id = ?'); $s->execute([$id]);
        $row = StoredRow::fetch($s); return $row === false ? null : $this->hydrate($row);
    }
    public function save(Event $event): Event
    {
        if ($event->id === null) {
            $this->pdo->prepare('INSERT INTO events (name, description, archived, revision) VALUES (?, ?, ?, 1)')->execute([$event->name, $event->description, (int) $event->archived]);
            return new Event((int) $this->pdo->lastInsertId(), $event->name, $event->description, $event->archived);
        }
        $s = $this->pdo->prepare('UPDATE events SET name = ?, description = ?, archived = ?, revision = revision + 1 WHERE id = ? AND revision = ?');
        $s->execute([$event->name, $event->description, (int) $event->archived, $event->id, $event->revision]);
        if ($s->rowCount() !== 1) { throw new AccessException('revision_conflict', 409); }
        return new Event($event->id, $event->name, $event->description, $event->archived, $event->revision + 1);
    }
    /** @param array<string,scalar|null> $row */
    private function hydrate(array $row): Event
    {
        return new Event((int) $row['id'], (string) $row['name'], (string) $row['description'], (bool) $row['archived'], (int) $row['revision']);
    }
}
