<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'budget_month_id',
        'subcategory_id',
        'amount',
        'occurred_on',
        'note',
        'invoice_path',
    ];

    public function invoiceUrl(): ?string
    {
        if (! $this->invoice_path) {
            return null;
        }

        return asset('storage/'.$this->invoice_path);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function budgetMonth(): BelongsTo
    {
        return $this->belongsTo(BudgetMonth::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}
