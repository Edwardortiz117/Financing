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

        $categoryData = $categories->map(function (Category $category) use ($allocationMap) {
            $subs = $category->subcategories->map(function (Subcategory $sub) use ($allocationMap) {
                $allocation = $allocationMap->get($sub->id);

                return [
                    'id' => $sub->id,
                    'slug' => $sub->slug,
                    'name' => $sub->name,
                    'max' => $sub->default_max,
                    'amount' => (float) ($allocation?->amount ?? $sub->default_amount),
                ];
            });

            return [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'color' => $category->color,
                'total' => $subs->sum('amount'),
                'subcategories' => $subs,
            ];
        });

        $planned = (float) $categoryData->sum('total');
        $actual = (float) $budget->transactions->sum('amount');
        $income = (float) $budget->income;
        $plannedSurplus = $income - $planned;
        $actualSurplus = $income - $actual;

        return [
            'budget' => [
                'id' => $budget->id,
                'year' => $budget->year,
                'month' => $budget->month,
                'label' => $budget->label(),
                'income' => $income,
            ],
            'categories' => $categoryData,
            'metrics' => [
                'planned_expenses' => $planned,
                'planned_surplus' => $plannedSurplus,
                'planned_savings_rate' => $income > 0 ? round(($plannedSurplus / $income) * 100) : 0,
                'actual_expenses' => $actual,
                'actual_surplus' => $actualSurplus,
                'actual_savings_rate' => $income > 0 ? round(($actualSurplus / $income) * 100) : 0,
            ],
            'transactions' => $budget->transactions
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
}
