<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Trip\ActualExpense;

interface ActualExpenseRepositoryInterface
{
    public function save(ActualExpense $expense): ActualExpense;

    public function update(ActualExpense $expense): ActualExpense;

    public function delete(int $id): void;

    /** @return ActualExpense[] */
    public function findByTripId(int $tripId): array;

    public function getById(int $id): ?ActualExpense;

    public function deleteByTripId(int $tripId): void;
}

