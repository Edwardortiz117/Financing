<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetMonth extends Model
{
    protected $fillable = [
        'year',
        'month',
        'income',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'income' => 'decimal:2',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function label(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function plannedExpenses(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function actualExpenses(): float
    {
        return (float) $this->transactions()->sum('amount');
    }
}
