{{-- Shared action modals --}}
<div id="modal-backdrop" class="fixed inset-0 z-50 hidden items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" role="presentation">
    {{-- Carga automática --}}
    <div id="modal-auto" class="modal-sheet hidden w-full max-w-md overflow-auto rounded-t-[24px] bg-card p-5 shadow-xl sm:rounded-[24px]" role="dialog" aria-modal="true" aria-labelledby="modal-auto-title">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="modal-auto-title" class="text-lg font-semibold">Carga automática</h3>
            <button type="button" data-close-modal class="rounded-lg px-2 py-1 text-muted hover:text-ink">Cerrar</button>
        </div>
        <form id="form-auto" class="space-y-3">
            <div>
                <label class="mb-1 block text-xs text-muted" for="auto-invoice">Factura (JPG, PNG o PDF)</label>
                <input type="file" id="auto-invoice" accept="image/*,.pdf,application/pdf" class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
                <p id="auto-invoice-status" class="mt-2 text-xs text-muted">Al cargar la factura se detectará el total automáticamente.</p>
                <div id="auto-invoice-candidates" class="mt-2 flex flex-wrap gap-2"></div>
                <div id="auto-invoice-preview" class="mt-3 hidden overflow-hidden rounded-xl border border-border bg-surface">
                    <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                        <p id="auto-invoice-preview-name" class="truncate text-xs text-muted"></p>
                        <button type="button" id="auto-invoice-preview-clear" class="text-xs text-bad">Quitar</button>
                    </div>
                    <div id="auto-invoice-preview-body" class="flex max-h-56 min-h-32 items-center justify-center p-2"></div>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="auto-subcategory">Categoría</label>
                <select id="auto-subcategory" required class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand"></select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="auto-amount">Monto (COP)</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted">$</span>
                    <input type="number" id="auto-amount" min="1" step="1" inputmode="numeric" required placeholder="0" class="money-input w-full rounded-xl border border-border bg-surface py-3 pr-4 pl-7 text-base tabular-nums outline-none focus:border-brand">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="auto-date">Fecha</label>
                <input type="date" id="auto-date" required class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand">
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="auto-note">Nota (opcional)</label>
                <input type="text" id="auto-note" maxlength="255" class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand">
            </div>
            <button type="submit" class="w-full rounded-xl bg-brand py-3 text-sm font-semibold text-white hover:bg-brand-dark">Registrar gasto</button>
        </form>
    </div>

    {{-- Carga manual --}}
    <div id="modal-manual" class="modal-sheet hidden w-full max-w-md overflow-auto rounded-t-[24px] bg-card p-5 shadow-xl sm:rounded-[24px]" role="dialog" aria-modal="true" aria-labelledby="modal-manual-title">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="modal-manual-title" class="text-lg font-semibold">Carga manual</h3>
            <button type="button" data-close-modal class="rounded-lg px-2 py-1 text-muted hover:text-ink">Cerrar</button>
        </div>
        <form id="form-manual" class="space-y-3">
            <div>
                <label class="mb-1 block text-xs text-muted" for="manual-subcategory">Categoría</label>
                <select id="manual-subcategory" required class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand"></select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="manual-amount">Monto (COP)</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted">$</span>
                    <input type="number" id="manual-amount" min="1" step="1" inputmode="numeric" required placeholder="0" class="money-input w-full rounded-xl border border-border bg-surface py-3 pr-4 pl-7 text-base tabular-nums outline-none focus:border-brand">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="manual-date">Fecha</label>
                <input type="date" id="manual-date" required class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand">
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted" for="manual-note">Nota (opcional)</label>
                <input type="text" id="manual-note" maxlength="255" class="w-full rounded-xl border border-border bg-surface px-3 py-3 text-sm outline-none focus:border-brand">
            </div>
            <button type="submit" class="w-full rounded-xl bg-hero py-3 text-sm font-semibold text-white">Registrar gasto</button>
        </form>
    </div>

    {{-- Ingreso --}}
    <div id="modal-income" class="modal-sheet hidden w-full max-w-md overflow-auto rounded-t-[24px] bg-card p-5 shadow-xl sm:rounded-[24px]" role="dialog" aria-modal="true" aria-labelledby="modal-income-title">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="modal-income-title" class="text-lg font-semibold">Crea ingreso</h3>
            <button type="button" data-close-modal class="rounded-lg px-2 py-1 text-muted hover:text-ink">Cerrar</button>
        </div>
        <form id="form-income" class="space-y-3">
            <div>
                <label class="mb-1 block text-xs text-muted" for="income-amount">Ingreso mensual (COP)</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted">$</span>
                    <input type="number" id="income-amount" min="0" step="1" inputmode="numeric" required placeholder="0" class="money-input w-full rounded-xl border border-border bg-surface py-3 pr-4 pl-7 text-base tabular-nums outline-none focus:border-brand">
                </div>
            </div>
            <button type="submit" class="w-full rounded-xl bg-brand py-3 text-sm font-semibold text-white hover:bg-brand-dark">Guardar ingreso</button>
        </form>
    </div>
</div>
