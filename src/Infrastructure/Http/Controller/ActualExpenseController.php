<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\ActualExpenseService;
use App\Application\NotFoundException;
use App\Application\ValidationException;
use App\Infrastructure\Http\JsonResponder;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ActualExpenseController
{
    public function __construct(private ActualExpenseService $service)
    {
    }

    /**
     * @throws JsonException
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $expenses = $this->service->getExpenses((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->expensesToArray($expenses));
    }

    /**
     * @throws JsonException
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();

        try {
            $expense = $this->service->addExpense((int) $id, $payload);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->expenseToArray($expense), 201);
    }

    /**
     * @throws JsonException
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id, string $expenseId): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();

        try {
            $expense = $this->service->updateExpense((int) $expenseId, $payload);
        } catch (ValidationException $exception) {
            return JsonResponder::write($response, [
                'error' => 'validation_error',
                'details' => $exception->errors(),
            ], 422);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, $this->expenseToArray($expense));
    }

    /**
     * @throws JsonException
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, string $id, string $expenseId): ResponseInterface
    {
        try {
            $this->service->deleteExpense((int) $expenseId);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, ['status' => 'deleted']);
    }

    /**
     * @throws JsonException
     */
    public function settlement(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        try {
            $settlement = $this->service->getSettlement((int) $id);
        } catch (NotFoundException) {
            return JsonResponder::write($response, ['error' => 'not_found'], 404);
        }

        return JsonResponder::write($response, [
            'totalRevenue' => $settlement['totalRevenue'],
            'plannedCostItems' => $settlement['plannedCostItems'],
            'totalPlannedCosts' => $settlement['totalPlannedCosts'],
            'totalAdditionalExpenses' => $settlement['totalAdditionalExpenses'],
            'totalAllExpenses' => $settlement['totalAllExpenses'],
            'surplus' => $settlement['surplus'],
            'expenses' => $this->expensesToArray($settlement['expenses']),
        ]);
    }

    private function expensesToArray(array $expenses): array
    {
        return array_map(fn ($e) => $this->expenseToArray($e), $expenses);
    }

    private function expenseToArray($expense): array
    {
        return [
            'id' => $expense->id(),
            'tripId' => $expense->tripId(),
            'label' => $expense->label(),
            'amount' => $expense->amount(),
        ];
    }
}


