@extends('layouts.mobile')

@section('title', 'Inicio')

@section('content')
    <div
        id="mobile-app"
        data-page="home"
        data-payload='@json($payload)'
        data-update-url="{{ $updateUrl }}"
        data-store-tx-url="{{ $storeTxUrl }}"
        data-parse-invoice-url="{{ $parseInvoiceUrl }}"
        data-year="{{ $year }}"
        data-month="{{ $month }}"
        class="px-4 pt-5"
    >
        <header class="mb-5 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand text-lg font-semibold text-white shadow-sm">
                P
            </div>
            <div>
                <p class="text-sm text-muted">Financing</p>
                <h1 class="text-lg font-semibold text-ink">Bienvenido Usuario</h1>
            </div>
        </header>

        <section class="relative overflow-hidden rounded-[22px] bg-hero px-5 py-5 text-white shadow-sm">
            <div class="relative z-10 max-w-[70%]">
                <p class="text-sm text-white/70">Gastos del mes</p>
                <p id="home-month-total" class="mt-1 text-3xl font-semibold tabular-nums tracking-tight">$ 0</p>
                <div id="home-mom-badge" class="mt-3 inline-flex items-center gap-1 rounded-full bg-[#5c3a32] px-2.5 py-1 text-xs text-[#f3b8a8]">
                    <span id="home-mom-value">—</span>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-2 bottom-0 h-28 w-28 opacity-90" aria-hidden="true">
                <svg viewBox="0 0 120 120" class="h-full w-full">
                    <circle cx="62" cy="70" r="28" fill="#2f2f2f"/>
                    <circle cx="62" cy="48" r="16" fill="#3a3a3a"/>
                    <rect x="42" y="78" width="40" height="28" rx="12" fill="#3a3a3a"/>
                </svg>
            </div>
        </section>

        <section class="mt-5 grid grid-cols-3 gap-3">
            <button type="button" data-open-modal="auto" class="flex flex-col items-center gap-2 rounded-2xl bg-card px-2 py-4 text-center shadow-sm ring-1 ring-border">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface text-ink">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 0 1 .88-7.9A5 5 0 1 1 16 9h1a4 4 0 0 1 0 8H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6m0 0 2.5-2.5M12 18l-2.5-2.5"/></svg>
                </span>
                <span class="text-[11px] font-medium leading-tight text-ink">Carga automática</span>
            </button>
            <button type="button" data-open-modal="manual" class="flex flex-col items-center gap-2 rounded-2xl bg-card px-2 py-4 text-center shadow-sm ring-1 ring-border">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface text-ink">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6M9 11h6M9 15h4M7 4h10a1 1 0 0 1 1 1v14l-3-2-3 2-3-2-3 2V5a1 1 0 0 1 1-1z"/></svg>
                </span>
                <span class="text-[11px] font-medium leading-tight text-ink">Carga manual</span>
            </button>
            <button type="button" data-open-modal="income" class="flex flex-col items-center gap-2 rounded-2xl bg-card px-2 py-4 text-center shadow-sm ring-1 ring-border">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface text-ink">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m4-14H9.5a2.5 2.5 0 0 0 0 5h5a2.5 2.5 0 0 1 0 5H8"/></svg>
                </span>
                <span class="text-[11px] font-medium leading-tight text-ink">Crea ingreso</span>
            </button>
        </section>

        <section class="mt-6">
            <a href="{{ route('expenses', ['year' => $year, 'month' => $month, 'tab' => 'categorias']) }}" class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-ink">Gastos por categoría</h2>
                <svg class="h-5 w-5 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
            </a>
            <div class="rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
                <div class="flex items-center gap-4">
                    <div id="home-category-list" class="min-w-0 flex-1 space-y-3"></div>
                    <div id="home-donut" class="shrink-0"></div>
                </div>
            </div>
        </section>

        <section class="mt-6 mb-2">
            <h2 class="mb-3 text-base font-semibold text-muted">Gastos recientes</h2>
            <div id="home-recent-list" class="space-y-3"></div>
        </section>

        <p id="save-status" class="mb-4 min-h-5 text-center text-xs text-muted"></p>
    </div>
@endsection
