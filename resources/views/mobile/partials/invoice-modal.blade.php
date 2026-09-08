<div id="invoice-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="invoice-modal-title">
    <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-card shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
            <h3 id="invoice-modal-title" class="truncate text-sm font-medium text-ink">Vista previa de factura</h3>
            <div class="flex shrink-0 items-center gap-3">
                <a id="invoice-modal-open" href="#" target="_blank" rel="noopener" class="text-xs text-muted underline hover:text-ink">Abrir</a>
                <button type="button" id="invoice-modal-close" class="rounded-lg border border-border px-3 py-1.5 text-sm text-ink">Cerrar</button>
            </div>
        </div>
        <div id="invoice-modal-body" class="min-h-[40vh] flex-1 overflow-auto bg-surface p-3"></div>
    </div>
</div>
