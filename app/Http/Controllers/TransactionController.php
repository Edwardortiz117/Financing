<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    public function __construct(private BudgetService $budgets) {}

    public function store(Request $request, int $year, int $month): RedirectResponse|JsonResponse
    {
        abort_unless($month >= 1 && $month <= 12, 404);

        $validated = $request->validate([
            'subcategory_id' => ['required', 'integer', 'exists:subcategories,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'invoice' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,pdf',
            ],
        ]);

        $occurred = \Carbon\Carbon::parse($validated['occurred_on']);
        $budgetYear = (int) $occurred->year;
        $budgetMonth = (int) $occurred->month;

        $budget = $this->budgets->findOrCreateMonth($budgetYear, $budgetMonth);

        $invoicePath = null;
        if ($request->hasFile('invoice')) {
            $invoicePath = $request->file('invoice')->store('invoices', 'public');
        }

        $transaction = Transaction::create([
            'budget_month_id' => $budget->id,
            'subcategory_id' => $validated['subcategory_id'],
            'amount' => $validated['amount'],
            'occurred_on' => $validated['occurred_on'],
            'note' => $validated['note'] ?? null,
            'invoice_path' => $invoicePath,
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
            ->route('home', ['year' => $year, 'month' => $month])
            ->with('status', 'Gasto registrado.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse|JsonResponse
    {
        $budget = $transaction->budgetMonth;

        if ($transaction->invoice_path) {
            Storage::disk('public')->delete($transaction->invoice_path);
        }

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
            ->route('home', ['year' => $budget->year, 'month' => $budget->month])
            ->with('status', 'Gasto eliminado.');
    }
}
