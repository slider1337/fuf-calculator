<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\ActualExpenseRepositoryInterface;
use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\Port\TripRepositoryInterface;
use App\Domain\Trip\ActualExpense;

/** @phpstan-type Settlement array{totalRevenue:float,participantCount:int,registrationCount:int,plannedCostItems:list<array{label:string,amount:float}>,totalPlannedCosts:float,totalAdditionalExpenses:float,totalAllExpenses:float,surplus:float,expenses:array<\App\Domain\Trip\ActualExpense>} */
final class ActualExpenseService
{
    public function __construct(
        private ActualExpenseRepositoryInterface $expenseRepository,
        private TripRepositoryInterface $tripRepository,
        private RegistrationRepositoryInterface $registrationRepository,
        private AccessPolicy $policy
    ) {
    }

    /** @param array<array-key,mixed> $payload */
    public function addExpense(int $tripId, array $payload): ActualExpense
    {
        return $this->policy->transaction(fn () => $this->addExpenseInternal($tripId, $payload));
    }

    /** @param array<array-key,mixed> $payload */
    private function addExpenseInternal(int $tripId, array $payload): ActualExpense
    {
        $this->policy->trip($tripId, 'expense.manage', true);
        $this->assertTripExists($tripId);
        $this->validatePayload($payload);

        $expense = new ActualExpense(
            id: null,
            tripId: $tripId,
            label: (string) Input::scalar($payload['label']),
            amount: (float) Input::scalar($payload['amount'])
        );

        return $this->expenseRepository->save($expense);
    }

    /** @param array<array-key,mixed> $payload */
    public function updateExpense(int $expenseId, array $payload, ?int $tripId = null): ActualExpense
    {
        return $this->policy->transaction(fn () => $this->updateExpenseInternal($expenseId, $payload, $tripId));
    }

    /** @param array<array-key,mixed> $payload */
    private function updateExpenseInternal(int $expenseId, array $payload, ?int $tripId = null): ActualExpense
    {
        $existing = $this->expenseRepository->getById($expenseId);
        if ($existing === null) {
            throw new NotFoundException('Actual expense not found.');
        }

        $this->assertExpenseTrip($existing->tripId(), $tripId);
        $this->policy->trip($existing->tripId(), 'expense.manage', true);
        $this->validatePayload($payload);

        $updated = new ActualExpense(
            id: $expenseId,
            tripId: $existing->tripId(),
            label: (string) Input::scalar($payload['label']),
            amount: (float) Input::scalar($payload['amount'])
        );

        return $this->expenseRepository->update($updated);
    }

    public function deleteExpense(int $expenseId, ?int $tripId = null): void
    {
        $this->policy->transaction(fn () => $this->deleteExpenseInternal($expenseId, $tripId));
    }

    private function deleteExpenseInternal(int $expenseId, ?int $tripId = null): void
    {
        $existing = $this->expenseRepository->getById($expenseId);
        if ($existing === null) {
            throw new NotFoundException('Actual expense not found.');
        }

        $this->assertExpenseTrip($existing->tripId(), $tripId);
        $this->policy->trip($existing->tripId(), 'expense.manage', true);
        $this->expenseRepository->delete($expenseId);
    }

    /** @return ActualExpense[] */
    public function getExpenses(int $tripId): array
    {
        $this->policy->trip($tripId, 'expense.view');
        $this->assertTripExists($tripId);

        return $this->expenseRepository->findByTripId($tripId);
    }

    /**
     * Settlement = total billing revenue from registrations vs planned costs + actual expenses.
     * @return Settlement
     */
    public function getSettlement(int $tripId): array
    {
        $this->policy->trip($tripId, 'settlement.view');
        $snapshot = $this->policy->metadata($tripId)['settlement_snapshot'] ?? null;
        if ($snapshot !== null) {
            return $this->settlementSnapshot($snapshot,$this->expenseRepository->findByTripId($tripId));
        }
        $trip = $this->tripRepository->getById($tripId);
        if ($trip === null) {
            throw new NotFoundException('Trip not found.');
        }

        $expenses = $this->expenseRepository->findByTripId($tripId);
        $registrations = $this->registrationRepository->findByTripId($tripId);

        // Planned costs from trip data
        $plannedCostItems = [];
        $totalPlannedCosts = 0.0;
        $nights = $trip->nights();

        foreach ($trip->bookings() as $booking) {
            if ($booking->count() === 0) {
                continue;
            }
            $cost = round($booking->basePricePerPerson() * $booking->count() * $nights, 2, PHP_ROUND_HALF_UP);
            $plannedCostItems[] = [
                'label' => 'Zimmer: ' . $this->categoryLabel($booking->categoryType()->value)
                    . ' (' . $booking->count() . ' × ' . number_format($booking->basePricePerPerson(), 2, '.', '') . ' € × ' . $nights . ' Nächte)',
                'amount' => $cost,
            ];
            $totalPlannedCosts = round($totalPlannedCosts + $cost, 2, PHP_ROUND_HALF_UP);
        }

        $policy = $trip->pricingPolicy();
        $spaTaxCount = $trip->spaTaxCount();

        if ($spaTaxCount > 0 && $policy->spaTaxPerPerson() > 0 && $nights > 0) {
            $spaTaxTotal = round($policy->spaTaxPerPerson() * $spaTaxCount * $nights, 2, PHP_ROUND_HALF_UP);
            $plannedCostItems[] = [
                'label' => 'Kurabgabe (' . $spaTaxCount . ' × ' . number_format($policy->spaTaxPerPerson(), 2, '.', '') . ' € × ' . $nights . ' Nächte)',
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
            'participantCount' => array_sum(array_map(static fn ($registration) => count(array_filter($registration->participants(), static fn($p) => $p->status() === 'confirmed')), $registrations)),
            'registrationCount' => count(array_filter($registrations, static fn($r) => count(array_filter($r->participants(), static fn($p) => $p->status() === 'confirmed')) > 0)),
            'plannedCostItems' => $plannedCostItems,
            'totalPlannedCosts' => $totalPlannedCosts,
            'totalAdditionalExpenses' => $totalAdditionalExpenses,
            'totalAllExpenses' => $totalAllExpenses,
            'surplus' => $surplus,
            'expenses' => $expenses,
        ];
    }

    private function assertExpenseTrip(int $actual, ?int $requested): void
    {
        if ($requested !== null && $requested !== $actual) { throw new NotFoundException('Actual expense not found.'); }
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

    /** @param array<array-key,mixed> $payload */
    private function validatePayload(array $payload): void
    {
        $errors = [];

        if (!array_key_exists('label', $payload) || trim((string) Input::scalar(($payload['label'] ?? ''))) === '') {
            $errors['label'] = 'required';
        }

        if (!array_key_exists('amount', $payload)) {
            $errors['amount'] = 'required';
        } elseif (!is_numeric($payload['amount']) || (float) Input::scalar($payload['amount']) < 0) {
            $errors['amount'] = 'must_be_non_negative_number';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @param array<ActualExpense> $expenses
     * @return Settlement
     */
    private function settlementSnapshot(string $json,array $expenses): array
    {
        $data=Input::map(json_decode($json,true,512,JSON_THROW_ON_ERROR));
        $items=[];
        foreach(Input::objects($data['plannedCostItems']??null) as $item){
            $items[]=['label'=>Input::text($item['label']??null),'amount'=>self::snapshotNumber($item['amount']??null)];
        }
        $participants=$data['participantCount']??null;$registrations=$data['registrationCount']??null;
        if(!is_int($participants)||!is_int($registrations))throw new \UnexpectedValueException('Invalid settlement counts');
        return ['totalRevenue'=>self::snapshotNumber($data['totalRevenue']??null),'participantCount'=>$participants,'registrationCount'=>$registrations,
            'plannedCostItems'=>$items,'totalPlannedCosts'=>self::snapshotNumber($data['totalPlannedCosts']??null),
            'totalAdditionalExpenses'=>self::snapshotNumber($data['totalAdditionalExpenses']??null),'totalAllExpenses'=>self::snapshotNumber($data['totalAllExpenses']??null),
            'surplus'=>self::snapshotNumber($data['surplus']??null),'expenses'=>$expenses];
    }
    private static function snapshotNumber(mixed $value): float
    {
        if((!is_int($value)&&!is_float($value))||!is_finite((float)$value))throw new \UnexpectedValueException('Invalid settlement amount');
        return (float)$value;
    }
}


