<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\BudgetMonth;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function findOrCreateMonth(int $year, int $month): BudgetMonth
    {
        $budget = BudgetMonth::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['income' => 3_800_000]
        );

        $this->ensureAllocations($budget);

        return $budget->fresh(['allocations', 'transactions.subcategory.category']);
    }

    public function ensureAllocations(BudgetMonth $budget): void
    {
        $existing = $budget->allocations()->pluck('subcategory_id')->all();
        $subcategories = Subcategory::query()->orderBy('sort_order')->get();

        foreach ($subcategories as $sub) {
            if (! in_array($sub->id, $existing, true)) {
                BudgetAllocation::create([
                    'budget_month_id' => $budget->id,
                    'subcategory_id' => $sub->id,
                    'amount' => $sub->default_amount,
                ]);
            }
        }
    }

    public function updateBudget(BudgetMonth $budget, float $income, array $allocations): BudgetMonth
    {
        return DB::transaction(function () use ($budget, $income, $allocations) {
            $budget->update(['income' => $income]);

            foreach ($allocations as $item) {
                BudgetAllocation::query()
                    ->where('budget_month_id', $budget->id)
                    ->where('subcategory_id', $item['subcategory_id'])
                    ->update(['amount' => $item['amount']]);
            }

            return $budget->fresh(['allocations', 'transactions.subcategory.category']);
        });
    }

    public function availableMonths(): Collection
    {
        return BudgetMonth::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get(['id', 'year', 'month', 'income']);
    }

    public function dashboardPayload(BudgetMonth $budget): array
    {
        $categories = Category::query()
            ->with(['subcategories'])
            ->orderBy('sort_order')
            ->get();

        $allocationMap = $budget->allocations->keyBy('subcategory_id');
        $transactions = $budget->transactions;

        $actualBySub = $transactions
            ->groupBy('subcategory_id')
            ->map(fn (Collection $group) => (float) $group->sum('amount'));

        $countBySub = $transactions
            ->groupBy('subcategory_id')
            ->map(fn (Collection $group) => $group->count());

        $categoryData = $categories->map(function (Category $category) use ($allocationMap, $actualBySub, $countBySub) {
            $subs = $category->subcategories->map(function (Subcategory $sub) use ($allocationMap, $actualBySub, $countBySub) {
                $allocation = $allocationMap->get($sub->id);

                return [
                    'id' => $sub->id,
                    'slug' => $sub->slug,
                    'name' => $sub->name,
                    'max' => $sub->default_max,
                    'amount' => (float) ($allocation?->amount ?? $sub->default_amount),
                    'actual' => (float) ($actualBySub[$sub->id] ?? 0),
                    'invoice_count' => (int) ($countBySub[$sub->id] ?? 0),
                ];
            });

            $plannedTotal = (float) $subs->sum('amount');
            $actualTotal = (float) $subs->sum('actual');
            $invoiceCount = (int) $subs->sum('invoice_count');

            return [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'color' => $category->color,
                'total' => $plannedTotal,
                'planned_total' => $plannedTotal,
                'actual_total' => $actualTotal,
                'invoice_count' => $invoiceCount,
                'budget_usage_pct' => $plannedTotal > 0
                    ? (int) round(($actualTotal / $plannedTotal) * 100)
                    : ($actualTotal > 0 ? 100 : 0),
                'percent_of_actual' => 0,
                'subcategories' => $subs,
            ];
        });

        $planned = (float) $categoryData->sum('planned_total');
        $actual = (float) $transactions->sum('amount');
        $income = (float) $budget->income;
        $plannedSurplus = $income - $planned;
        $actualSurplus = $income - $actual;

        $uncategorizedAmount = (float) $transactions
            ->filter(fn ($tx) => ! $tx->subcategory_id || ! $tx->subcategory)
            ->sum('amount');
        $uncategorizedCount = $transactions
            ->filter(fn ($tx) => ! $tx->subcategory_id || ! $tx->subcategory)
            ->count();

        $categorizedActual = max(0, $actual - $uncategorizedAmount);
        $categorizedPct = $actual > 0 ? (int) round(($categorizedActual / $actual) * 100) : 100;

        $categoryData = $categoryData->map(function (array $cat) use ($actual) {
            $cat['percent_of_actual'] = $actual > 0
                ? (int) round(($cat['actual_total'] / $actual) * 100)
                : 0;

            return $cat;
        });

        $prev = $this->previousMonthKey($budget->year, $budget->month);
        $prevBudget = BudgetMonth::query()
            ->where('year', $prev['year'])
            ->where('month', $prev['month'])
            ->with('transactions')
            ->first();
        $prevActual = $prevBudget ? (float) $prevBudget->transactions->sum('amount') : null;
        $monthOverMonthPct = null;
        if ($prevActual !== null && $prevActual > 0) {
            $monthOverMonthPct = round((($actual - $prevActual) / $prevActual) * 100, 1);
        } elseif ($prevActual !== null && $prevActual == 0.0 && $actual > 0) {
            $monthOverMonthPct = 100.0;
        }

        return [
            'budget' => [
                'id' => $budget->id,
                'year' => $budget->year,
                'month' => $budget->month,
                'label' => $budget->label(),
                'income' => $income,
            ],
            'categories' => $categoryData->values(),
            'metrics' => [
                'planned_expenses' => $planned,
                'planned_surplus' => $plannedSurplus,
                'planned_savings_rate' => $income > 0 ? (int) round(($plannedSurplus / $income) * 100) : 0,
                'actual_expenses' => $actual,
                'actual_surplus' => $actualSurplus,
                'actual_savings_rate' => $income > 0 ? (int) round(($actualSurplus / $income) * 100) : 0,
                'month_over_month_pct' => $monthOverMonthPct,
                'categorized_pct' => $categorizedPct,
                'uncategorized_amount' => $uncategorizedAmount,
                'uncategorized_count' => $uncategorizedCount,
            ],
            'transactions' => $transactions
                ->sortByDesc('occurred_on')
                ->values()
                ->map(fn ($tx) => [
                    'id' => $tx->id,
                    'amount' => (float) $tx->amount,
                    'occurred_on' => $tx->occurred_on->format('Y-m-d'),
                    'note' => $tx->note,
                    'subcategory_id' => $tx->subcategory_id,
                    'subcategory_name' => $tx->subcategory?->name,
                    'category_name' => $tx->subcategory?->category?->name,
                    'category_color' => $tx->subcategory?->category?->color,
                    'invoice_url' => $tx->invoiceUrl(),
                    'invoice_is_pdf' => $tx->invoice_path
                        ? str_ends_with(strtolower($tx->invoice_path), '.pdf')
                        : false,
                ]),
            'months' => $this->availableMonths()->map(fn (BudgetMonth $m) => [
                'year' => $m->year,
                'month' => $m->month,
                'label' => $m->label(),
            ]),
        ];
    }

    /**
     * @return array{year: int, month: int}
     */
    private function previousMonthKey(int $year, int $month): array
    {
        if ($month === 1) {
            return ['year' => $year - 1, 'month' => 12];
        }

        return ['year' => $year, 'month' => $month - 1];
    }
}
