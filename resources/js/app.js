const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function formatCurrency(num) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
    }).format(Number(num) || 0);
}

function metricClass(surplus, savingsRate) {
    if (surplus < 0) return 'text-xl font-medium tabular-nums sm:text-2xl md:text-[26px] text-[#f44336]';
    if (savingsRate >= 20) return 'text-xl font-medium tabular-nums sm:text-2xl md:text-[26px] text-[#4caf50]';
    return 'text-xl font-medium tabular-nums sm:text-2xl md:text-[26px] text-white';
}

function shiftMonth(year, month, delta) {
    const d = new Date(year, month - 1 + delta, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('budget-app');
    if (!root) return;

    let payload = JSON.parse(root.dataset.payload);
    const updateUrl = root.dataset.updateUrl;
    const storeTxUrl = root.dataset.storeTxUrl;
    const parseInvoiceUrl = root.dataset.parseInvoiceUrl;
    let year = Number(root.dataset.year);
    let month = Number(root.dataset.month);
    let saveTimer = null;
    let dirty = false;

    const els = {
        plannedExpenses: document.getElementById('val-planned-expenses'),
        plannedSurplus: document.getElementById('val-planned-surplus'),
        plannedSavings: document.getElementById('val-planned-savings'),
        actualExpenses: document.getElementById('val-actual-expenses'),
        actualSurplus: document.getElementById('val-actual-surplus'),
        actualSavings: document.getElementById('val-actual-savings'),
        progressBar: document.getElementById('progress-bar'),
        categoryList: document.getElementById('category-display-list'),
        sliders: document.getElementById('sliders-section'),
        income: document.getElementById('income-input'),
        saveStatus: document.getElementById('save-status'),
        monthPicker: document.getElementById('month-picker'),
        txForm: document.getElementById('tx-form'),
        txSub: document.getElementById('tx-subcategory'),
        txAmount: document.getElementById('tx-amount'),
        txDate: document.getElementById('tx-date'),
        txNote: document.getElementById('tx-note'),
        txInvoice: document.getElementById('tx-invoice'),
        txInvoiceStatus: document.getElementById('tx-invoice-status'),
        txInvoiceCandidates: document.getElementById('tx-invoice-candidates'),
        txList: document.getElementById('tx-list'),
    };

    function navigateTo(y, m) {
        window.location.href = `/budgets/${y}/${m}`;
    }

    document.getElementById('btn-prev-month')?.addEventListener('click', () => {
        const next = shiftMonth(year, month, -1);
        navigateTo(next.year, next.month);
    });

    document.getElementById('btn-next-month')?.addEventListener('click', () => {
        const next = shiftMonth(year, month, 1);
        navigateTo(next.year, next.month);
    });

    els.monthPicker?.addEventListener('change', (e) => {
        const [y, m] = e.target.value.split('-').map(Number);
        if (y && m) navigateTo(y, m);
    });

    function applyMetrics() {
        const m = payload.metrics;
        els.plannedExpenses.textContent = formatCurrency(m.planned_expenses);
        els.plannedSurplus.textContent = formatCurrency(m.planned_surplus);
        els.plannedSavings.textContent = `${m.planned_savings_rate}%`;
        els.plannedSurplus.className = metricClass(m.planned_surplus, m.planned_savings_rate);
        els.plannedSavings.className = metricClass(m.planned_surplus, m.planned_savings_rate);

        els.actualExpenses.textContent = formatCurrency(m.actual_expenses);
        els.actualSurplus.textContent = formatCurrency(m.actual_surplus);
        els.actualSavings.textContent = `${m.actual_savings_rate}%`;
        els.actualSurplus.className = metricClass(m.actual_surplus, m.actual_savings_rate);
        els.actualSavings.className = metricClass(m.actual_surplus, m.actual_savings_rate);
    }

    function recalculateLocal() {
        const income = Number(els.income.value) || 0;
        let planned = 0;

        payload.categories.forEach((cat) => {
            cat.total = cat.subcategories.reduce((sum, s) => sum + Number(s.amount), 0);
            planned += cat.total;
            const display = document.getElementById(`display-total-${cat.id}`);
            if (display) display.textContent = formatCurrency(cat.total);
        });

        const plannedSurplus = income - planned;
        const plannedRate = income > 0 ? Math.round((plannedSurplus / income) * 100) : 0;
        payload.budget.income = income;
        payload.metrics.planned_expenses = planned;
        payload.metrics.planned_surplus = plannedSurplus;
        payload.metrics.planned_savings_rate = plannedRate;

        els.progressBar.innerHTML = '';
        payload.categories.forEach((cat) => {
            const pct = planned > 0 ? (cat.total / planned) * 100 : 0;
            if (pct > 0) {
                els.progressBar.insertAdjacentHTML(
                    'beforeend',
                    `<div class="h-full" style="width:${pct}%;background-color:${cat.color}"></div>`
                );
            }
        });

        applyMetrics();
    }

    function renderCategoriesAndSliders() {
        els.categoryList.innerHTML = '';
        els.sliders.innerHTML = '';
        els.txSub.innerHTML = '';

        payload.categories.forEach((cat, index) => {
            els.categoryList.insertAdjacentHTML(
                'beforeend',
                `<div class="flex items-center justify-between border-b border-[#333] py-4 text-[15px]">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color:${cat.color}"></span>
                        ${cat.name}
                    </div>
                    <div id="display-total-${cat.id}" class="tabular-nums">${formatCurrency(cat.total)}</div>
                </div>`
            );

            let subsHtml = '';
            cat.subcategories.forEach((sub) => {
                els.txSub.insertAdjacentHTML(
                    'beforeend',
                    `<option value="${sub.id}">${cat.name} — ${sub.name}</option>`
                );

                subsHtml += `
                    <div class="min-w-[200px] flex-1">
                        <span class="mb-2 block text-sm text-[#a0a0a0]">${sub.name}</span>
                        <div class="flex items-center gap-3">
                            <input type="range" min="0" max="${sub.max}" value="${sub.amount}"
                                   data-sub-id="${sub.id}" data-cat-id="${cat.id}" class="sub-slider flex-1">
                            <input type="number" min="0" step="1" value="${sub.amount}"
                                   data-sub-id="${sub.id}" data-cat-id="${cat.id}"
                                   class="sub-input min-w-[9.5rem] w-36 rounded-md bg-[#1e1e1e] px-3 py-2.5 text-right text-sm tabular-nums text-white">
                        </div>
                    </div>`;
            });

            const open = index < 2;
            els.sliders.insertAdjacentHTML(
                'beforeend',
                `<button type="button" class="accordion flex w-full items-center justify-between border-b border-[#333] py-4 text-left text-base ${open ? 'active' : ''}" data-open="${open}">
                    ${cat.name}
                    <span class="accordion-icon text-xl">${open ? '˄' : '˅'}</span>
                </button>
                <div class="panel overflow-hidden transition-[max-height] duration-200" style="max-height:${open ? '500px' : '0'}">
                    <div class="flex flex-wrap gap-5 py-4">${subsHtml}</div>
                </div>`
            );
        });

        els.sliders.querySelectorAll('.accordion').forEach((btn) => {
            btn.addEventListener('click', () => {
                const panel = btn.nextElementSibling;
                const isOpen = btn.classList.contains('active');
                btn.classList.toggle('active', !isOpen);
                btn.querySelector('.accordion-icon').textContent = isOpen ? '˅' : '˄';
                panel.style.maxHeight = isOpen ? '0' : `${panel.scrollHeight}px`;
            });
        });

        els.sliders.querySelectorAll('.sub-slider, .sub-input').forEach((input) => {
            input.addEventListener('input', () => {
                const subId = Number(input.dataset.subId);
                const value = Number(input.value) || 0;
                payload.categories.forEach((cat) => {
                    cat.subcategories.forEach((sub) => {
                        if (sub.id === subId) sub.amount = value;
                    });
                });
                const slider = els.sliders.querySelector(`.sub-slider[data-sub-id="${subId}"]`);
                const number = els.sliders.querySelector(`.sub-input[data-sub-id="${subId}"]`);
                if (slider) slider.value = value;
                if (number) number.value = value;
                recalculateLocal();
                scheduleSave();
            });
        });
    }

    function renderTransactions() {
        if (!payload.transactions.length) {
            els.txList.innerHTML = '<p class="text-sm text-[#a0a0a0]">No hay gastos registrados este mes.</p>';
            return;
        }

        els.txList.innerHTML = payload.transactions
            .map(
                (tx) => `
            <div class="flex items-start justify-between gap-3 border-b border-[#333] py-3 text-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2 w-2 rounded-full" style="background-color:${tx.category_color || '#a0a0a0'}"></span>
                        <span>${tx.subcategory_name}</span>
                    </div>
                    <div class="mt-1 text-xs text-[#a0a0a0]">
                        ${tx.occurred_on}${tx.note ? ' · ' + tx.note : ''}
                        ${tx.invoice_url ? ` · <a href="${tx.invoice_url}" target="_blank" rel="noopener" class="text-white underline hover:no-underline">Ver factura</a>` : ''}
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="tabular-nums">${formatCurrency(tx.amount)}</span>
                    <button type="button" data-tx-id="${tx.id}" class="tx-delete text-xs text-[#f44336] hover:underline">Eliminar</button>
                </div>
            </div>`
            )
            .join('');

        els.txList.querySelectorAll('.tx-delete').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.txId;
                els.saveStatus.textContent = 'Eliminando…';
                const res = await fetch(`/transactions/${id}`, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (data.ok) {
                    payload = data.payload;
                    hydrate();
                    els.saveStatus.textContent = 'Gasto eliminado.';
                } else {
                    els.saveStatus.textContent = 'No se pudo eliminar.';
                }
            });
        });
    }

    function hydrate() {
        els.income.value = payload.budget.income;
        const today = new Date();
        const inMonth =
            today.getFullYear() === year && today.getMonth() + 1 === month
                ? today.toISOString().slice(0, 10)
                : `${year}-${String(month).padStart(2, '0')}-01`;
        els.txDate.value = inMonth;
        renderCategoriesAndSliders();
        recalculateLocal();
        renderTransactions();
        applyMetrics();
    }

    async function persist() {
        dirty = false;
        els.saveStatus.textContent = 'Guardando…';

        const allocations = [];
        payload.categories.forEach((cat) => {
            cat.subcategories.forEach((sub) => {
                allocations.push({ subcategory_id: sub.id, amount: Number(sub.amount) || 0 });
            });
        });

        try {
            const res = await fetch(updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    income: Number(els.income.value) || 0,
                    allocations,
                }),
            });

            if (!res.ok) throw new Error('save failed');
            const data = await res.json();
            if (data.payload) {
                payload = data.payload;
                applyMetrics();
                renderTransactions();
            }
            els.saveStatus.textContent = 'Guardado.';
        } catch {
            els.saveStatus.textContent = 'Error al guardar. Revisa la conexión.';
            dirty = true;
        }
    }

    function scheduleSave() {
        dirty = true;
        els.saveStatus.textContent = 'Cambios pendientes…';
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persist, 400);
    }

    els.income.addEventListener('input', () => {
        recalculateLocal();
        scheduleSave();
    });

    function renderInvoiceCandidates(candidates) {
        if (!els.txInvoiceCandidates) return;
        els.txInvoiceCandidates.innerHTML = '';

        const amounts = (candidates || [])
            .map((c) => (typeof c === 'object' ? Number(c.amount) : Number(c)))
            .filter((n) => Number.isFinite(n) && n > 0);

        if (!amounts.length) return;

        const title = document.createElement('span');
        title.className = 'mr-1 w-full text-xs text-[#a0a0a0]';
        title.textContent = 'Si el total no es correcto, elige uno:';
        els.txInvoiceCandidates.appendChild(title);

        amounts.forEach((amount, index) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'rounded-md border border-[#333] bg-[#121212] px-3 py-1.5 text-xs tabular-nums text-white hover:border-[#a0a0a0]';
            btn.textContent = `${index === 0 ? '★ ' : ''}${formatCurrency(amount)}`;
            btn.addEventListener('click', () => {
                els.txAmount.value = amount;
                els.txInvoiceStatus.textContent = `Monto seleccionado: ${formatCurrency(amount)}`;
                els.saveStatus.textContent = 'Monto actualizado. Puedes registrar el gasto.';
            });
            els.txInvoiceCandidates.appendChild(btn);
        });
    }

    async function parseInvoiceFile(file) {
        if (!file || !parseInvoiceUrl) return;

        els.txInvoiceStatus.textContent = 'Leyendo factura y detectando total…';
        els.saveStatus.textContent = 'Analizando factura…';
        if (els.txInvoiceCandidates) els.txInvoiceCandidates.innerHTML = '';

        const formData = new FormData();
        formData.append('invoice', file);

        try {
            const res = await fetch(parseInvoiceUrl, {
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
                els.txAmount.value = data.amount;
                els.txInvoiceStatus.textContent = data.message || `Total detectado: ${formatCurrency(data.amount)}`;
                els.saveStatus.textContent = 'Monto cargado desde la factura. Revisa y registra el gasto.';
                renderInvoiceCandidates(candidates);
                return;
            }

            if (candidates.length) {
                const first = typeof candidates[0] === 'object' ? candidates[0].amount : candidates[0];
                els.txAmount.value = first;
                els.txInvoiceStatus.textContent =
                    data.message || 'Revisa el monto sugerido; el OCR no encontró una etiqueta clara de total.';
                els.saveStatus.textContent = 'Monto sugerido desde la factura.';
                renderInvoiceCandidates(candidates);
                return;
            }

            els.txInvoiceStatus.textContent =
                data.message || 'No se detectó el total. Escríbelo manualmente.';
            els.saveStatus.textContent = 'Factura cargada; completa el monto.';
        } catch {
            els.txInvoiceStatus.textContent = 'Error al leer la factura. Puedes ingresar el monto a mano.';
            els.saveStatus.textContent = 'No se pudo analizar la factura.';
        }
    }

    els.txInvoice?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (!file) {
            els.txInvoiceStatus.textContent =
                'Al cargar la factura se intentará detectar el total automáticamente.';
            if (els.txInvoiceCandidates) els.txInvoiceCandidates.innerHTML = '';
            return;
        }
        parseInvoiceFile(file);
    });

    els.txForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        els.saveStatus.textContent = 'Registrando gasto…';

        const formData = new FormData();
        formData.append('subcategory_id', String(Number(els.txSub.value)));
        formData.append('amount', String(Number(els.txAmount.value)));
        formData.append('occurred_on', els.txDate.value);
        if (els.txNote.value) {
            formData.append('note', els.txNote.value);
        }
        if (els.txInvoice?.files?.[0]) {
            formData.append('invoice', els.txInvoice.files[0]);
        }

        try {
            const res = await fetch(storeTxUrl, {
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

            const txYear = Number(els.txDate.value.slice(0, 4));
            const txMonth = Number(els.txDate.value.slice(5, 7));
            if (txYear !== year || txMonth !== month) {
                navigateTo(txYear, txMonth);
                return;
            }

            payload = data.payload;
            els.txAmount.value = '';
            els.txNote.value = '';
            if (els.txInvoice) els.txInvoice.value = '';
            if (els.txInvoiceStatus) {
                els.txInvoiceStatus.textContent =
                    'Al cargar la factura se intentará detectar el total automáticamente.';
            }
            if (els.txInvoiceCandidates) els.txInvoiceCandidates.innerHTML = '';
            hydrate();
            els.saveStatus.textContent = 'Gasto registrado.';
        } catch {
            els.saveStatus.textContent = 'No se pudo registrar el gasto.';
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    hydrate();
});
