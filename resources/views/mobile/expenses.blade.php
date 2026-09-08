@extends('layouts.mobile')

@section('title', 'Mis gastos')

@section('content')
    <div
        id="mobile-app"
        data-page="expenses"
        data-tab="{{ $tab }}"
        data-payload='@json($payload)'
        data-update-url="{{ $updateUrl }}"
        data-store-tx-url="{{ $storeTxUrl }}"
        data-parse-invoice-url="{{ $parseInvoiceUrl }}"
        data-year="{{ $year }}"
        data-month="{{ $month }}"
        class="px-4 pt-5"
    >
        <header class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-ink">Mis gastos</h1>
            <button type="button" data-open-modal="manual" class="flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-card text-ink shadow-sm" aria-label="Agregar gasto">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            </button>
        </header>

        <div class="mb-4 flex rounded-2xl bg-[#ececec] p-1">
            <a href="{{ route('expenses', ['year' => $year, 'month' => $month, 'tab' => 'facturas']) }}" class="flex-1 rounded-xl px-3 py-2.5 text-center text-sm font-medium {{ $tab === 'facturas' ? 'bg-hero text-white shadow-sm' : 'text-muted' }}">Facturas</a>
            <a href="{{ route('expenses', ['year' => $year, 'month' => $month, 'tab' => 'categorias']) }}" class="flex-1 rounded-xl px-3 py-2.5 text-center text-sm font-medium {{ $tab === 'categorias' ? 'bg-hero text-white shadow-sm' : 'text-muted' }}">Categorías</a>
            <span class="flex-1 cursor-not-allowed rounded-xl px-3 py-2.5 text-center text-sm font-medium text-muted/50" title="Próximamente">Productos</span>
        </div>

        <div class="mb-4 flex gap-2">
            <label class="flex flex-1 items-center gap-2 rounded-2xl bg-card px-3 py-2.5 text-sm text-ink shadow-sm ring-1 ring-border">
                <span class="text-muted">Filtrar</span>
                <input type="month" id="expenses-month" value="{{ sprintf('%04d-%02d', $year, $month) }}" class="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none">
            </label>
            <label class="flex flex-1 items-center gap-2 rounded-2xl bg-card px-3 py-2.5 text-sm text-ink shadow-sm ring-1 ring-border">
                <span class="text-muted">Orden</span>
                <select id="expenses-sort" class="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none">
                    <option value="amount">Valor total</option>
                    <option value="date">Fecha</option>
                    <option value="name">Nombre</option>
                </select>
            </label>
        </div>

        <div id="expenses-facturas" class="{{ $tab === 'facturas' ? '' : 'hidden' }} space-y-3"></div>

        <div id="expenses-categorias" class="{{ $tab === 'categorias' ? '' : 'hidden' }} space-y-4">
            <div class="rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink">Gastos por categoría</h2>
                    <span class="text-muted" title="Distribución del gasto real del mes">ⓘ</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p id="expenses-total" class="text-2xl font-semibold tabular-nums text-ink">$ 0</p>
                        <div id="expenses-legend" class="mt-3 space-y-1.5"></div>
                    </div>
                    <div id="expenses-donut" class="shrink-0"></div>
                </div>
                <button type="button" id="expenses-legend-more" class="mt-3 flex w-full items-center justify-center gap-1 text-sm text-muted">
                    Ver más
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>

            <div id="expenses-uncategorized" class="hidden rounded-[22px] bg-warn px-4 py-4 text-sm text-warn-text"></div>
            <div id="expenses-category-cards" class="space-y-3"></div>
        </div>

        <p id="save-status" class="mb-4 min-h-5 text-center text-xs text-muted"></p>
    </div>
@endsection
