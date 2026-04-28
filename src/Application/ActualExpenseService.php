<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\ActualExpenseRepositoryInterface;
use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\Port\TripRepositoryInterface;
use App\Domain\Trip\ActualExpense;

final class ActualExpenseService
{
    public function __construct(
        private ActualExpenseRepositoryInterface $expenseRepository,
        private TripRepositoryInterface $tripRepository,
        private RegistrationRepositoryInterface $registrationRepository
    ) {
    }

    public function addExpense(int $tripId, array $payload): ActualExpense
    {
        $this->assertTripExists($tripId);
        $this->validatePayload($payload);

        $expense = new ActualExpense(
            id: null,
            tripId: $tripId,
            label: (string) $payload['label'],
            amount: (float) $payload['amount']
        );

        return $this->expenseRepository->save($expense);
    }

    public function updateExpense(int $expenseId, array $payload): ActualExpense
    {
        $existing = $this->expenseRepository->getById($expenseId);
        if ($existing === null) {
            throw new NotFoundException('Actual expense not found.');
        }

        $this->validatePayload($payload);

        $updated = new ActualExpense(
            id: $expenseId,
            tripId: $existing->tripId(),
            label: (string) $payload['label'],
            amount: (float) $payload['amount']
        );

        return $this->expenseRepository->update($updated);
    }

    public function deleteExpense(int $expenseId): void
    {
        $existing = $this->expenseRepository->getById($expenseId);
        if ($existing === null) {
            throw new NotFoundException('Actual expense not found.');
        }

        $this->expenseRepository->delete($expenseId);
    }

    /** @return ActualExpense[] */
    public function getExpenses(int $tripId): array
    {
        $this->assertTripExists($tripId);

        return $this->expenseRepository->findByTripId($tripId);
    }

    /**
     * Settlement = total billing revenue from registrations vs planned costs + actual expenses.
     */
    public function getSettlement(int $tripId): array
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $expenses = $this->expenseRepository->findByTripId($tripId);
        $registrations = $this->registrationRepository->findByTripId($tripId);

        // Planned costs from trip data
        $plannedCostItems = [];
        $totalPlannedCosts = 0.0;

        foreach ($trip->bookings() as $booking) {
            if ($booking->count() === 0) {
                continue;
            }
            $cost = round($booking->basePricePerPerson() * $booking->count(), 2, PHP_ROUND_HALF_UP);
            $plannedCostItems[] = [
                'label' => 'Zimmer: ' . $this->categoryLabel($booking->categoryType()->value)
                    . ' (' . $booking->count() . ' × ' . number_format($booking->basePricePerPerson(), 2, '.', '') . ' €)',
                'amount' => $cost,
            ];
            $totalPlannedCosts = round($totalPlannedCosts + $cost, 2, PHP_ROUND_HALF_UP);
        }

        $policy = $trip->pricingPolicy();
        $totalParticipants = 0;
        foreach ($trip->bookings() as $booking) {
            $totalParticipants += $booking->count();
        }

        if ($totalParticipants > 0 && $policy->spaTaxPerPerson() > 0) {
            $spaTaxTotal = round($policy->spaTaxPerPerson() * $totalParticipants, 2, PHP_ROUND_HALF_UP);
            $plannedCostItems[] = [
                'label' => 'Kurabgabe (' . $totalParticipants . ' × ' . number_format($policy->spaTaxPerPerson(), 2, '.', '') . ' €)',
                'amount' => $spaTaxTotal,
            ];
            $totalPlannedCosts = round($totalPlannedCosts + $spaTaxTotal, 2, PHP_ROUND_HALF_UP);
        }

        foreach ($trip->groupExpenses() as $groupExpense) {
            $plannedCostItems[] = [
                'label' => 'Gruppenausgabe: ' . $groupExpense->label(),
                'amount' => $groupExpense->amount(),
            ];
            $totalPlannedCosts = round($totalPlannedCosts + $groupExpense->amount(), 2, PHP_ROUND_HALF_UP);
        }

        // Additional actual expenses (manually entered)
        $totalAdditionalExpenses = 0.0;
        foreach ($expenses as $expense) {
            $totalAdditionalExpenses = round($totalAdditionalExpenses + $expense->amount(), 2, PHP_ROUND_HALF_UP);
        }

        $totalAllExpenses = round($totalPlannedCosts + $totalAdditionalExpenses, 2, PHP_ROUND_HALF_UP);

        // Revenue from billing
        $totalRevenue = 0.0;
        foreach ($registrations as $registration) {
            $billingTotal = $registration->billingTotal();
            if ($billingTotal !== null) {
                $totalRevenue = round($totalRevenue + $billingTotal, 2, PHP_ROUND_HALF_UP);
            }
        }

        $surplus = round($totalRevenue - $totalAllExpenses, 2, PHP_ROUND_HALF_UP);

        return [
            'totalRevenue' => $totalRevenue,
            'plannedCostItems' => $plannedCostItems,
            'totalPlannedCosts' => $totalPlannedCosts,
            'totalAdditionalExpenses' => $totalAdditionalExpenses,
            'totalAllExpenses' => $totalAllExpenses,
            'surplus' => $surplus,
            'expenses' => $expenses,
        ];
    }

    private function categoryLabel(string $type): string
    {
        return match ($type) {
            'ADULT_DOUBLE' => 'Erwachsener DZ',
            'ADULT_MULTI' => 'Erwachsener MBZ',
            'CHILD' => 'Kind',
            default => $type,
        };
    }

    private function assertTripExists(int $tripId): void
    {
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }
    }

    private function validatePayload(array $payload): void
    {
        $errors = [];

        if (!array_key_exists('label', $payload) || trim((string) ($payload['label'] ?? '')) === '') {
            $errors['label'] = 'required';
        }

        if (!array_key_exists('amount', $payload)) {
            $errors['amount'] = 'required';
        } elseif (!is_numeric($payload['amount']) || (float) $payload['amount'] < 0) {
            $errors['amount'] = 'must_be_non_negative_number';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}


