<?php

namespace App\Http\Controllers;

use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $budgets) {}

    public function index(Request $request): View
    {
        return $this->mobilePage($request, 'home', 'mobile.home');
    }

    public function expenses(Request $request): View
    {
        $tab = $request->query('tab', 'categorias');
        if (! in_array($tab, ['facturas', 'categorias', 'productos'], true)) {
            $tab = 'categorias';
        }

        return $this->mobilePage($request, 'expenses', 'mobile.expenses', [
            'tab' => $tab,
        ]);
    }

    public function more(Request $request): View
    {
        return $this->mobilePage($request, 'more', 'mobile.more');
    }

    public function show(int $year, int $month): RedirectResponse
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        abort_unless($year >= 2000 && $year <= 2100, 404);

        return redirect()->route('home', ['year' => $year, 'month' => $month]);
    }

    public function update(Request $request, int $year, int $month): JsonResponse
    {
        abort_unless($month >= 1 && $month <= 12, 404);

        $validated = $request->validate([
            'income' => ['required', 'numeric', 'min:0'],
            'allocations' => ['required', 'array'],
            'allocations.*.subcategory_id' => ['required', 'integer', 'exists:subcategories,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $budget = $this->budgets->findOrCreateMonth($year, $month);
        $budget = $this->budgets->updateBudget(
            $budget,
            (float) $validated['income'],
            $validated['allocations']
        );

        return response()->json([
            'ok' => true,
            'payload' => $this->budgets->dashboardPayload($budget),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function mobilePage(Request $request, string $activeNav, string $view, array $extra = []): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        abort_unless($month >= 1 && $month <= 12, 404);
        abort_unless($year >= 2000 && $year <= 2100, 404);

        $budget = $this->budgets->findOrCreateMonth($year, $month);
        $payload = $this->budgets->dashboardPayload($budget);

        return view($view, array_merge([
            'payload' => $payload,
            'year' => $year,
            'month' => $month,
            'activeNav' => $activeNav,
            'updateUrl' => route('budgets.update', ['year' => $year, 'month' => $month]),
            'storeTxUrl' => route('transactions.store', ['year' => $year, 'month' => $month]),
            'parseInvoiceUrl' => route('invoices.parse'),
        ], $extra));
    }
}
