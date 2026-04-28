<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\ActualExpenseRepositoryInterface;
use App\Domain\Trip\ActualExpense;
use PDO;

final class SqliteActualExpenseRepository implements ActualExpenseRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(ActualExpense $expense): ActualExpense
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO actual_expenses (trip_id, label, amount) VALUES (:tripId, :label, :amount)'
        );

        $stmt->execute([
            ':tripId' => $expense->tripId(),
            ':label' => $expense->label(),
            ':amount' => $expense->amount(),
        ]);

        return $expense->withId((int) $this->pdo->lastInsertId());
    }

    public function update(ActualExpense $expense): ActualExpense
    {
        $stmt = $this->pdo->prepare(
            'UPDATE actual_expenses SET label = :label, amount = :amount WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $expense->id(),
            ':label' => $expense->label(),
            ':amount' => $expense->amount(),
        ]);

        return $expense;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM actual_expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** @return ActualExpense[] */
    public function findByTripId(int $tripId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM actual_expenses WHERE trip_id = :tripId ORDER BY id ASC');
        $stmt->execute([':tripId' => $tripId]);
        $rows = $stmt->fetchAll();

        $expenses = [];
        foreach ($rows as $row) {
            $expenses[] = new ActualExpense(
                id: (int) $row['id'],
                tripId: (int) $row['trip_id'],
                label: (string) $row['label'],
                amount: (float) $row['amount']
            );
        }

        return $expenses;
    }

    public function getById(int $id): ?ActualExpense
    {
        $stmt = $this->pdo->prepare('SELECT * FROM actual_expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new ActualExpense(
            id: (int) $row['id'],
            tripId: (int) $row['trip_id'],
            label: (string) $row['label'],
            amount: (float) $row['amount']
        );
    }

    public function deleteByTripId(int $tripId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM actual_expenses WHERE trip_id = :tripId');
        $stmt->execute([':tripId' => $tripId]);
    }
}

