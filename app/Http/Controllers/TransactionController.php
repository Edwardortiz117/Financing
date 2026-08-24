<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private BudgetService $budgets) {}

    public function store(Request $request, int $year, int $month): RedirectResponse|JsonResponse
    {
        abort_unless($month >= 1 && $month <= 12, 404);

        $validated = $request->validate([
            'subcategory_id' => ['required', 'integer', 'exists:subcategories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $occurred = \Carbon\Carbon::parse($validated['occurred_on']);
        $budgetYear = (int) $occurred->year;
        $budgetMonth = (int) $occurred->month;

        // Always attach to the month of the transaction date
        $budget = $this->budgets->findOrCreateMonth($budgetYear, $budgetMonth);

        $transaction = Transaction::create([
            'budget_month_id' => $budget->id,
            'subcategory_id' => $validated['subcategory_id'],
            'amount' => $validated['amount'],
            'occurred_on' => $validated['occurred_on'],
            'note' => $validated['note'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'transaction' => $transaction,
                'payload' => $this->budgets->dashboardPayload(
                    $this->budgets->findOrCreateMonth($year, $month)
                ),
            ]);
        }

        return redirect()
            ->route('budgets.show', ['year' => $year, 'month' => $month])
            ->with('status', 'Gasto registrado.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse|JsonResponse
    {
        $budget = $transaction->budgetMonth;
        $transaction->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'payload' => $this->budgets->dashboardPayload(
                    $budget->fresh(['allocations', 'transactions.subcategory.category'])
                ),
            ]);
        }

        return redirect()
            ->route('budgets.show', ['year' => $budget->year, 'month' => $budget->month])
            ->with('status', 'Gasto eliminado.');
    }
}
