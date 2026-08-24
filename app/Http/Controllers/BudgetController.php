<?php

namespace App\Http\Controllers;

use App\Models\BudgetMonth;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $budgets) {}

    public function index(): View
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        return $this->show($year, $month);
    }

    public function show(int $year, int $month): View
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        abort_unless($year >= 2000 && $year <= 2100, 404);

        $budget = $this->budgets->findOrCreateMonth($year, $month);
        $payload = $this->budgets->dashboardPayload($budget);

        return view('budget.dashboard', [
            'payload' => $payload,
            'year' => $year,
            'month' => $month,
        ]);
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
}
