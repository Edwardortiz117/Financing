import {
    allocationsFromPayload,
    csrfToken,
    defaultDateInMonth,
    fillSubcategorySelect,
    formatCurrency,
    isPdfFile,
    setStatus,
} from '../lib/utils.js';

let previewObjectUrl = null;
let modalObjectUrl = null;

function revokePreview() {
    if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
    }
}

function revokeModalUrl() {
    if (modalObjectUrl) {
        URL.revokeObjectURL(modalObjectUrl);
        modalObjectUrl = null;
    }
}

function buildPreviewMarkup(url, pdf) {
    if (pdf) {
        return `<iframe src="${url}#toolbar=1" title="Vista previa PDF" class="h-48 w-full rounded-md border border-border bg-white"></iframe>`;
    }
    return `<img src="${url}" alt="Vista previa" class="max-h-48 w-auto max-w-full rounded-md object-contain">`;
}

function buildModalMarkup(url, pdf) {
    if (pdf) {
        return `<iframe src="${url}#toolbar=1" title="Factura PDF" class="h-[70vh] w-full rounded-md border border-border bg-white"></iframe>`;
    }
    return `<img src="${url}" alt="Factura" class="mx-auto max-h-[70vh] w-auto max-w-full rounded-md object-contain">`;
}

export function openInvoiceModal(url, title, isObjectUrl = false) {
    const modal = document.getElementById('invoice-modal');
    const body = document.getElementById('invoice-modal-body');
    const titleEl = document.getElementById('invoice-modal-title');
    const openLink = document.getElementById('invoice-modal-open');
    if (!modal || !body) return;

    if (!isObjectUrl) revokeModalUrl();

    const pdf = isPdfFile(url);
    if (titleEl) titleEl.textContent = title || 'Vista previa de factura';
    body.innerHTML = buildModalMarkup(url, pdf);
    if (openLink) {
        openLink.href = url;
        openLink.classList.toggle('hidden', !url);
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

export function closeInvoiceModal() {
    const modal = document.getElementById('invoice-modal');
    const body = document.getElementById('invoice-modal-body');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (body) body.innerHTML = '';
    revokeModalUrl();
    document.body.classList.remove('overflow-hidden');
}

function showModal(name) {
    const backdrop = document.getElementById('modal-backdrop');
    ['auto', 'manual', 'income'].forEach((key) => {
        document.getElementById(`modal-${key}`)?.classList.add('hidden');
    });
    document.getElementById(`modal-${name}`)?.classList.remove('hidden');
    backdrop?.classList.remove('hidden');
    backdrop?.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

export function closeActionModals() {
    const backdrop = document.getElementById('modal-backdrop');
    ['auto', 'manual', 'income'].forEach((key) => {
        document.getElementById(`modal-${key}`)?.classList.add('hidden');
    });
    backdrop?.classList.add('hidden');
    backdrop?.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

function clearAutoPreview() {
    revokePreview();
    const wrap = document.getElementById('auto-invoice-preview');
    const body = document.getElementById('auto-invoice-preview-body');
    const name = document.getElementById('auto-invoice-preview-name');
    if (wrap) wrap.classList.add('hidden');
    if (body) body.innerHTML = '';
    if (name) name.textContent = '';
}

function showAutoPreview(file) {
    const wrap = document.getElementById('auto-invoice-preview');
    const body = document.getElementById('auto-invoice-preview-body');
    const name = document.getElementById('auto-invoice-preview-name');
    if (!wrap || !body) return;

    revokePreview();
    previewObjectUrl = URL.createObjectURL(file);
    if (name) name.textContent = file.name;
    body.innerHTML = buildPreviewMarkup(previewObjectUrl, isPdfFile(file));
    wrap.classList.remove('hidden');
}

function renderInvoiceCandidates(candidates, amountInput, statusEl) {
    const box = document.getElementById('auto-invoice-candidates');
    if (!box) return;
    box.innerHTML = '';

    const amounts = (candidates || [])
        .map((c) => (typeof c === 'object' ? Number(c.amount) : Number(c)))
        .filter((n) => Number.isFinite(n) && n > 0);

    if (!amounts.length) return;

    const title = document.createElement('span');
    title.className = 'mr-1 w-full text-xs text-muted';
    title.textContent = 'Si el total no es correcto, elige uno:';
    box.appendChild(title);

    amounts.forEach((amount, index) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className =
            'rounded-lg border border-border bg-surface px-3 py-1.5 text-xs tabular-nums text-ink hover:border-brand';
        btn.textContent = `${index === 0 ? '★ ' : ''}${formatCurrency(amount)}`;
        btn.addEventListener('click', () => {
            if (amountInput) amountInput.value = String(amount);
            if (statusEl) statusEl.textContent = `Monto seleccionado: ${formatCurrency(amount)}`;
        });
        box.appendChild(btn);
    });
}

async function parseInvoiceFile(file, ctx) {
    const statusEl = document.getElementById('auto-invoice-status');
    const amountInput = document.getElementById('auto-amount');
    if (!file || !ctx.parseInvoiceUrl) return;

    if (statusEl) statusEl.textContent = 'Leyendo factura y detectando total…';
    setStatus('Analizando factura…');
    document.getElementById('auto-invoice-candidates').innerHTML = '';

    const formData = new FormData();
    formData.append('invoice', file);

    try {
        const res = await fetch(ctx.parseInvoiceUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });
        const data = await res.json();
        const candidates = data.candidates || [];

        if (data.ok && data.amount) {
            if (amountInput) amountInput.value = String(data.amount);
            if (statusEl) statusEl.textContent = data.message || `Total detectado: ${formatCurrency(data.amount)}`;
            setStatus('Monto cargado desde la factura.');
            renderInvoiceCandidates(candidates, amountInput, statusEl);
            return;
        }

        if (candidates.length) {
            const first = typeof candidates[0] === 'object' ? candidates[0].amount : candidates[0];
            if (amountInput) amountInput.value = String(first);
            if (statusEl) {
                statusEl.textContent =
                    data.message || 'Revisa el monto sugerido; no se encontró una etiqueta clara de total.';
            }
            renderInvoiceCandidates(candidates, amountInput, statusEl);
            return;
        }

        if (statusEl) statusEl.textContent = data.message || 'No se detectó el total. Escríbelo manualmente.';
        setStatus('Factura cargada; completa el monto.');
    } catch {
        if (statusEl) statusEl.textContent = 'Error al leer la factura. Puedes ingresar el monto a mano.';
        setStatus('No se pudo analizar la factura.');
    }
}

async function storeTransaction(ctx, fields) {
    const formData = new FormData();
    formData.append('subcategory_id', String(fields.subcategoryId));
    formData.append('amount', String(fields.amount));
    formData.append('occurred_on', fields.date);
    if (fields.note) formData.append('note', fields.note);
    if (fields.invoice) formData.append('invoice', fields.invoice);

    const res = await fetch(ctx.storeTxUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error('tx failed');
    return data;
}

async function persistBudget(ctx, income) {
    const res = await fetch(ctx.updateUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            income: Number(income) || 0,
            allocations: allocationsFromPayload(ctx.payload),
        }),
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error('save failed');
    return data;
}

export function initActions(ctx) {
    fillSubcategorySelect(document.getElementById('auto-subcategory'), ctx.payload.categories);
    fillSubcategorySelect(document.getElementById('manual-subcategory'), ctx.payload.categories);

    const dateDefault = defaultDateInMonth(ctx.year, ctx.month);
    const autoDate = document.getElementById('auto-date');
    const manualDate = document.getElementById('manual-date');
    if (autoDate) autoDate.value = dateDefault;
    if (manualDate) manualDate.value = dateDefault;

    const incomeInput = document.getElementById('income-amount');
    if (incomeInput) incomeInput.value = String(ctx.payload.budget.income || 0);

    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const name = btn.getAttribute('data-open-modal');
            if (name === 'income' && incomeInput) {
                incomeInput.value = String(ctx.payload.budget.income || 0);
            }
            showModal(name);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', closeActionModals);
    });

    document.getElementById('modal-backdrop')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeActionModals();
    });

    document.getElementById('invoice-modal-close')?.addEventListener('click', closeInvoiceModal);
    document.getElementById('invoice-modal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeInvoiceModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeInvoiceModal();
            closeActionModals();
        }
    });

    const autoInvoice = document.getElementById('auto-invoice');
    autoInvoice?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        const statusEl = document.getElementById('auto-invoice-status');
        if (!file) {
            if (statusEl) {
                statusEl.textContent = 'Al cargar la factura se detectará el total automáticamente.';
            }
            document.getElementById('auto-invoice-candidates').innerHTML = '';
            clearAutoPreview();
            return;
        }
        showAutoPreview(file);
        parseInvoiceFile(file, ctx);
    });

    document.getElementById('auto-invoice-preview-clear')?.addEventListener('click', () => {
        if (autoInvoice) autoInvoice.value = '';
        clearAutoPreview();
        document.getElementById('auto-invoice-candidates').innerHTML = '';
        const statusEl = document.getElementById('auto-invoice-status');
        if (statusEl) statusEl.textContent = 'Al cargar la factura se detectará el total automáticamente.';
    });

    document.getElementById('form-auto')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setStatus('Registrando gasto…');
        try {
            const data = await storeTransaction(ctx, {
                subcategoryId: Number(document.getElementById('auto-subcategory').value),
                amount: Number(document.getElementById('auto-amount').value),
                date: document.getElementById('auto-date').value,
                note: document.getElementById('auto-note').value,
                invoice: autoInvoice?.files?.[0] || null,
            });
            ctx.payload = data.payload;
            closeActionModals();
            setStatus('Gasto registrado.');
            ctx.onPayloadChange?.(ctx.payload);
            window.location.reload();
        } catch {
            setStatus('No se pudo registrar el gasto.');
        }
    });

    document.getElementById('form-manual')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setStatus('Registrando gasto…');
        try {
            const data = await storeTransaction(ctx, {
                subcategoryId: Number(document.getElementById('manual-subcategory').value),
                amount: Number(document.getElementById('manual-amount').value),
                date: document.getElementById('manual-date').value,
                note: document.getElementById('manual-note').value,
                invoice: null,
            });
            ctx.payload = data.payload;
            closeActionModals();
            setStatus('Gasto registrado.');
            ctx.onPayloadChange?.(ctx.payload);
            window.location.reload();
        } catch {
            setStatus('No se pudo registrar el gasto.');
        }
    });

    document.getElementById('form-income')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setStatus('Guardando ingreso…');
        try {
            const income = Number(document.getElementById('income-amount').value) || 0;
            const data = await persistBudget(ctx, income);
            ctx.payload = data.payload;
            closeActionModals();
            setStatus('Ingreso guardado.');
            ctx.onPayloadChange?.(ctx.payload);
            window.location.reload();
        } catch {
            setStatus('No se pudo guardar el ingreso.');
        }
    });

    return { persistBudget, storeTransaction, openInvoiceModal };
}
