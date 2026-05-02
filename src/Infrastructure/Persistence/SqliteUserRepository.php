<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\UserRepositoryInterface;
use App\Domain\User\User;
use DateTimeImmutable;
use PDO;

final readonly class SqliteUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => strtolower($email)]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM users ORDER BY email')->fetchAll();
        return array_map(fn (array $row): User => $this->hydrate($row), $rows);
    }

    public function save(User $user): User
    {
        if ($user->id() === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (email, password_hash, role, created_at)
                 VALUES (:email, :password_hash, :role, :created_at)'
            );
            $statement->execute([
                ':email' => strtolower($user->email()),
                ':password_hash' => $user->passwordHash(),
                ':role' => $user->role(),
                ':created_at' => $user->createdAt()->format(DATE_ATOM),
            ]);

            return $user->withId((int) $this->pdo->lastInsertId());
        }

        $statement = $this->pdo->prepare(
            'UPDATE users SET email = :email, password_hash = :password_hash, role = :role WHERE id = :id'
        );
        $statement->execute([
            ':email' => strtolower($user->email()),
            ':password_hash' => $user->passwordHash(),
            ':role' => $user->role(),
            ':id' => $user->id(),
        ]);

        return $user;
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute([':id' => $id]);
    }

    public function countAdmins(): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
        $statement->execute([':role' => User::ROLE_ADMIN]);
        return (int) $statement->fetchColumn();
    }

    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            $row['password_hash'] !== null ? (string) $row['password_hash'] : null,
            (string) $row['role'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
