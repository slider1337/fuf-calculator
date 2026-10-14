<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;
use App\Application\Port\AccessRepositoryInterface;
use PDO;
use Throwable;

final readonly class SqliteAccessRepository implements AccessRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    private function table(string $type): array
    {
        return match ($type) {
            'trip' => ['trip_access', 'trip_id'],
            'event' => ['event_access', 'event_id'],
            'default' => ['event_default_team', 'event_id'],
            default => throw new \InvalidArgumentException('Unknown access target'),
        };
    }
    public function exists(string $type, int $id): bool
    {
        $table = $type === 'trip' ? 'trips' : 'events';
        $s = $this->pdo->prepare("SELECT 1 FROM $table WHERE id = ?");
        $s->execute([$id]); return $s->fetchColumn() !== false;
    }
    public function role(int $userId): ?string
    {
        $s = $this->pdo->prepare('SELECT role FROM users WHERE id = ?'); $s->execute([$userId]);
        return ($v = $s->fetchColumn()) === false ? null : (string) $v;
    }
    public function capabilities(string $type, int $id, int $userId): array
    {
        [$table, $key] = $this->table($type);
        $s = $this->pdo->prepare("SELECT capability FROM $table WHERE $key = ? AND user_id = ? ORDER BY capability");
        $s->execute([$id, $userId]); return $s->fetchAll(PDO::FETCH_COLUMN);
    }
    public function metadata(int $tripId): array
    {
        $s = $this->pdo->prepare('SELECT registration_mode, registration_mode_revision, archived_at, form_version_id, form_revision, event_id, closed_at, closed_by, settlement_snapshot, calculation_snapshot FROM trips WHERE id = ?');
        $s->execute([$tripId]); return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    public function setMetadata(int $tripId, array $values): void
    {
        $allowed = ['registration_mode', 'archived_at', 'event_id', 'closed_at', 'closed_by', 'settlement_snapshot', 'calculation_snapshot'];
        if (array_diff(array_keys($values), $allowed) !== []) { throw new \InvalidArgumentException('Invalid metadata'); }
        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($values)));
        $this->pdo->prepare("UPDATE trips SET $set WHERE id = ?")->execute([...array_values($values), $tripId]);
    }
    public function team(string $type, int $id): array
    {
        [$table, $key] = $this->table($type);
        $s = $this->pdo->prepare("SELECT a.user_id, a.capability, u.email FROM $table a JOIN users u ON u.id = a.user_id WHERE a.$key = ? ORDER BY u.email, a.capability");
        $s->execute([$id]); $members = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (int) $row['user_id'];
            $members[$uid] ??= ['userId' => $uid, 'email' => $row['email'], 'capabilities' => []];
            $members[$uid]['capabilities'][] = $row['capability'];
        }
        return array_values($members);
    }
    public function replaceMember(string $type, int $id, int $userId, array $capabilities): void
    {
        [$table, $key] = $this->table($type);
        $this->pdo->prepare("DELETE FROM $table WHERE $key = ? AND user_id = ?")->execute([$id, $userId]);
        $s = $this->pdo->prepare("INSERT INTO $table ($key, user_id, capability) VALUES (?, ?, ?)");
        foreach (array_unique($capabilities) as $capability) { $s->execute([$id, $userId, $capability]); }
    }
    public function audit(int $actor, string $action, string $type, int $id, string $reason): void
    {
        $this->pdo->prepare('INSERT INTO audit_events (actor_id, action, target_type, target_id, reason, created_at, correlation_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$actor, $action, $type, $id, $reason, gmdate(DATE_ATOM), bin2hex(random_bytes(16))]);
    }
    public function transaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) { return $operation(); }
        // Reserve the SQLite writer before checking rights/closure to prevent close/write races.
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE trips SET id = id WHERE 0');
            $result = $operation(); $this->pdo->commit(); return $result;
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }
    public function legacyCandidates(): array
    {
        return $this->pdo->query('SELECT c.trip_id AS tripId, t.name AS tripName, c.user_id AS userId, u.email FROM legacy_access_candidates c JOIN trips t ON t.id = c.trip_id JOIN users u ON u.id = c.user_id ORDER BY c.trip_id, c.user_id')->fetchAll(PDO::FETCH_ASSOC);
    }
}
