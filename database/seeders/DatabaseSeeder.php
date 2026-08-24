<?php

namespace Database\Seeders;

use App\Models\BudgetAllocation;
use App\Models\BudgetMonth;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'slug' => 'util',
                'name' => 'Servicios generales',
                'color' => '#81c784',
                'subs' => [
                    ['slug' => 'internet', 'name' => 'Internet', 'amount' => 80, 'max' => 200],
                    ['slug' => 'water', 'name' => 'Agua', 'amount' => 45, 'max' => 200],
                    ['slug' => 'electricity', 'name' => 'Electricidad', 'amount' => 45, 'max' => 200],
                ],
            ],
            [
                'slug' => 'dog',
                'name' => 'Cuidado y artículos para perros',
                'color' => '#ffd54f',
                'subs' => [
                    ['slug' => 'walker', 'name' => 'Paseador', 'amount' => 400, 'max' => 1000],
                    ['slug' => 'dogfood', 'name' => 'Comida/artículos', 'amount' => 250, 'max' => 800],
                ],
            ],
            [
                'slug' => 'trans',
                'name' => 'Gastos de transporte',
                'color' => '#e57373',
                'subs' => [
                    ['slug' => 'transport', 'name' => 'Transporte', 'amount' => 180, 'max' => 1000],
                ],
            ],
            [
                'slug' => 'food',
                'name' => 'Comida',
                'color' => '#9575cd',
                'subs' => [
                    ['slug' => 'groceries', 'name' => 'Mercado/Comida', 'amount' => 300, 'max' => 1500],
                ],
            ],
            [
                'slug' => 'health',
                'name' => 'Salud y gimnasio',
                'color' => '#f06292',
                'subs' => [
                    ['slug' => 'gym', 'name' => 'Salud/Gimnasio', 'amount' => 50, 'max' => 500],
                ],
            ],
            [
                'slug' => 'pers',
                'name' => 'Cuidado personal y suscripciones',
                'color' => '#4db6ac',
                'subs' => [
                    ['slug' => 'personal', 'name' => 'Cuidado personal', 'amount' => 80, 'max' => 500],
                ],
            ],
        ];

        foreach ($catalog as $catIndex => $catData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $catData['slug']],
                [
                    'name' => $catData['name'],
                    'color' => $catData['color'],
                    'sort_order' => $catIndex,
                ]
            );

            foreach ($catData['subs'] as $subIndex => $subData) {
                Subcategory::query()->updateOrCreate(
                    ['slug' => $subData['slug']],
                    [
                        'category_id' => $category->id,
                        'name' => $subData['name'],
                        'default_amount' => $subData['amount'],
                        'default_max' => $subData['max'],
                        'sort_order' => $subIndex,
                    ]
                );
            }
        }

        $year = (int) now()->year;
        $month = (int) now()->month;

        $budget = BudgetMonth::query()->firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['income' => 3800]
        );

        if ($budget->wasRecentlyCreated || $budget->allocations()->count() === 0) {
            $budget->update(['income' => 3800]);

            foreach (Subcategory::query()->get() as $sub) {
                BudgetAllocation::query()->updateOrCreate(
                    [
                        'budget_month_id' => $budget->id,
                        'subcategory_id' => $sub->id,
                    ],
                    ['amount' => $sub->default_amount]
                );
            }
        }
    }
}
