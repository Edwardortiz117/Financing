@extends('layouts.app')

@section('title', 'Panel de Presupuesto')

@section('content')
    <div
        id="budget-app"
        data-payload='@json($payload)'
        data-update-url="{{ route('budgets.update', ['year' => $year, 'month' => $month]) }}"
        data-store-tx-url="{{ route('transactions.store', ['year' => $year, 'month' => $month]) }}"
        data-year="{{ $year }}"
        data-month="{{ $month }}"
    >
        {{-- Month selector --}}
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-[#333] pb-6">
            <div>
                <h1 class="text-2xl font-medium">Panel de Presupuesto</h1>
                <p class="mt-1 text-sm text-[#a0a0a0]">Planificado, historial mensual y libro de gastos</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-prev-month" class="rounded-lg border border-[#333] bg-[#1e1e1e] px-3 py-2 text-sm hover:border-[#a0a0a0]">←</button>
                <input
                    type="month"
                    id="month-picker"
                    value="{{ sprintf('%04d-%02d', $year, $month) }}"
                    class="rounded-lg border border-[#333] bg-[#1e1e1e] px-3 py-2 text-sm text-white outline-none focus:border-[#a0a0a0]"
                >
                <button type="button" id="btn-next-month" class="rounded-lg border border-[#333] bg-[#1e1e1e] px-3 py-2 text-sm hover:border-[#a0a0a0]">→</button>
            </div>
        </div>

        <p id="save-status" class="mb-4 min-h-5 text-xs text-[#a0a0a0]"></p>

        {{-- Planned metrics --}}
        <p class="mb-3 text-xs uppercase tracking-wide text-[#a0a0a0]">Planificado (sliders)</p>
        <div class="mb-8 grid grid-cols-1 gap-4 border-b border-[#333] pb-6 text-center sm:grid-cols-3 sm:gap-2">
            <div class="sm:border-r sm:border-[#333] sm:pr-2">
                <div id="val-planned-expenses" class="text-xl font-medium tabular-nums sm:text-2xl md:text-[26px]">0</div>
                <div class="mt-1 text-xs text-[#a0a0a0] md:text-sm">Gastos planificados</div>
            </div>
            <div class="sm:border-r sm:border-[#333] sm:px-2">
                <div id="val-planned-surplus" class="text-xl font-medium tabular-nums sm:text-2xl md:text-[26px]">0</div>
                <div class="mt-1 text-xs text-[#a0a0a0] md:text-sm">Superávit / déficit</div>
            </div>
            <div class="sm:pl-2">
                <div id="val-planned-savings" class="text-xl font-medium tabular-nums sm:text-2xl md:text-[26px]">0%</div>
                <div class="mt-1 text-xs text-[#a0a0a0] md:text-sm">Tasa de ahorro</div>
            </div>
        </div>

        {{-- Actual metrics --}}
        <p class="mb-3 text-xs uppercase tracking-wide text-[#a0a0a0]">Real (libro de gastos)</p>
        <div class="mb-10 grid grid-cols-1 gap-4 border-b border-[#333] pb-6 text-center sm:grid-cols-3 sm:gap-2">
            <div class="sm:border-r sm:border-[#333] sm:pr-2">
                <div id="val-actual-expenses" class="text-xl font-medium tabular-nums sm:text-2xl md:text-[26px]">0</div>
                <div class="mt-1 text-xs text-[#a0a0a0] md:text-sm">Gastos reales</div>
            </div>
            <div class="sm:border-r sm:border-[#333] sm:px-2">
                <div id="val-actual-surplus" class="text-xl font-medium tabular-nums sm:text-2xl md:text-[26px]">0</div>
                <div class="mt-1 text-xs text-[#a0a0a0] md:text-sm">Superávit / déficit</div>
            </div>
            <div class="sm:pl-2">
                <div id="val-actual-savings" class="text-xl font-medium tabular-nums sm:text-2xl md:text-[26px]">0%</div>
                <div class="mt-1 text-xs text-[#a0a0a0] md:text-sm">Tasa de ahorro</div>
            </div>
        </div>

        {{-- Breakdown --}}
        <h2 class="mb-4 text-lg font-medium">Desglose de gastos planificados</h2>
        <div id="progress-bar" class="mb-5 flex h-4 w-full overflow-hidden rounded-lg bg-[#333]"></div>
        <div id="category-display-list" class="mb-10"></div>

        {{-- Income --}}
        <div class="mb-10">
            <h2 class="mb-2 text-lg font-medium">¿Cuánto ganas al mes?</h2>
            <p class="mb-2 text-sm text-[#a0a0a0]">Montos en pesos colombianos (COP), sin decimales.</p>
            <div class="relative max-w-md">
                <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-[#a0a0a0]">$</span>
                <input
                    type="number"
                    id="income-input"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="0"
                    class="money-input mt-2 w-full rounded-lg border border-[#333] bg-[#1e1e1e] py-3 pr-4 pl-8 text-base tabular-nums text-white outline-none focus:border-[#a0a0a0]"
                >
            </div>
        </div>

        {{-- Sliders --}}
        <h2 class="mb-4 text-lg font-medium">Ajusta tus gastos</h2>
        <div id="sliders-section" class="mb-12"></div>

        {{-- Transactions ledger --}}
        <h2 class="mb-2 text-lg font-medium">Libro de gastos</h2>
        <p class="mb-4 text-sm text-[#a0a0a0]">Registra gastos individuales con fecha. Se asocian al mes de la fecha.</p>

        <form id="tx-form" class="mb-6 grid gap-3 rounded-lg border border-[#333] bg-[#1e1e1e] p-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs text-[#a0a0a0]" for="tx-subcategory">Subcategoría</label>
                <select id="tx-subcategory" required class="w-full rounded-lg border border-[#333] bg-[#121212] px-3 py-2 text-sm outline-none focus:border-[#a0a0a0]"></select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#a0a0a0]" for="tx-amount">Monto (COP)</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-[#a0a0a0]">$</span>
                    <input type="number" id="tx-amount" min="1" step="1" inputmode="numeric" required placeholder="0" class="money-input w-full rounded-lg border border-[#333] bg-[#121212] py-3 pr-4 pl-7 text-base tabular-nums outline-none focus:border-[#a0a0a0]">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#a0a0a0]" for="tx-date">Fecha</label>
                <input type="date" id="tx-date" required class="w-full rounded-lg border border-[#333] bg-[#121212] px-3 py-2 text-sm outline-none focus:border-[#a0a0a0]">
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#a0a0a0]" for="tx-note">Nota (opcional)</label>
                <input type="text" id="tx-note" maxlength="255" class="w-full rounded-lg border border-[#333] bg-[#121212] px-3 py-2 text-sm outline-none focus:border-[#a0a0a0]">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-black hover:bg-gray-200">
                    Registrar gasto
                </button>
            </div>
        </form>

        <div id="tx-list" class="mb-10"></div>

        <p class="mt-8 text-xs leading-relaxed text-[#a0a0a0]">
            *El resumen se actualiza en vivo. Los cambios de ingreso y sliders se guardan automáticamente en PostgreSQL.
            La tasa de ahorro se vuelve verde al 20%+ y roja si estás en déficit.
        </p>
    </div>
@endsection
