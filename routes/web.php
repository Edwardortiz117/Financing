<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BudgetController::class, 'index'])->name('home');
Route::get('/gastos', [BudgetController::class, 'expenses'])->name('expenses');
Route::get('/mas', [BudgetController::class, 'more'])->name('more');

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

Route::post('/invoices/parse', [InvoiceController::class, 'parse'])
    ->name('invoices.parse');
