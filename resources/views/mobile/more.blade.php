@extends('layouts.mobile')

@section('title', 'Más')

@section('content')
    <div
        id="mobile-app"
        data-page="more"
        data-payload='@json($payload)'
        data-update-url="{{ $updateUrl }}"
        data-store-tx-url="{{ $storeTxUrl }}"
        data-parse-invoice-url="{{ $parseInvoiceUrl }}"
        data-year="{{ $year }}"
        data-month="{{ $month }}"
        class="px-4 pt-5"
    >
        <header class="mb-5">
            <h1 class="text-2xl font-semibold text-ink">Más</h1>
            <p class="mt-1 text-sm text-muted">Mes, ingreso y presupuestos planificados</p>
        </header>

        <section class="mb-4 rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
            <label class="mb-2 block text-sm font-medium text-ink" for="more-month">Mes</label>
            <input type="month" id="more-month" value="{{ sprintf('%04d-%02d', $year, $month) }}" class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand">
        </section>

        <section class="mb-4 rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
            <div class="mb-2 flex items-center justify-between">
                <label class="text-sm font-medium text-ink" for="more-income">Ingreso mensual (COP)</label>
                <button type="button" data-open-modal="income" class="text-sm font-medium text-brand">Editar</button>
            </div>
            <p id="more-income-display" class="text-2xl font-semibold tabular-nums">$ 0</p>
            <p class="mt-1 text-xs text-muted">Planificado vs real se calcula con este ingreso.</p>
        </section>

        <section class="mb-4 rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
            <h2 class="mb-1 text-sm font-semibold text-ink">Resumen del mes</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-surface p-3">
                    <p class="text-muted">Gastos reales</p>
                    <p id="more-actual" class="mt-1 font-semibold tabular-nums">$ 0</p>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <p class="text-muted">Presupuesto</p>
                    <p id="more-planned" class="mt-1 font-semibold tabular-nums">$ 0</p>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <p class="text-muted">Superávit</p>
                    <p id="more-surplus" class="mt-1 font-semibold tabular-nums">$ 0</p>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <p class="text-muted">Ahorro</p>
                    <p id="more-savings" class="mt-1 font-semibold tabular-nums">0%</p>
                </div>
            </div>
        </section>

        <section class="mb-2 rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
            <h2 class="mb-3 text-sm font-semibold text-ink">Ajusta tus presupuestos</h2>
            <div id="more-sliders" class="space-y-2"></div>
        </section>

        <p id="save-status" class="mb-4 min-h-5 text-center text-xs text-muted"></p>
    </div>
@endsection
