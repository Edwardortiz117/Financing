<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BudgetController::class, 'index'])->name('home');
Route::get('/budgets/{year}/{month}', [BudgetController::class, 'show'])
    ->whereNumber(['year', 'month'])
    ->name('budgets.show');
Route::put('/budgets/{year}/{month}', [BudgetController::class, 'update'])
    ->whereNumber(['year', 'month'])
    ->name('budgets.update');

Route::post('/budgets/{year}/{month}/transactions', [TransactionController::class, 'store'])
    ->whereNumber(['year', 'month'])
    ->name('transactions.store');
Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])
    ->name('transactions.destroy');
