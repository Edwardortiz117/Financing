import {
    allocationsFromPayload,
    csrfToken,
    formatCurrency,
    navigateMonth,
    setStatus,
} from '../lib/utils.js';

let saveTimer = null;

async function persist(ctx) {
    setStatus('Guardando…');
    try {
        const res = await fetch(ctx.updateUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                income: Number(ctx.payload.budget.income) || 0,
                allocations: allocationsFromPayload(ctx.payload),
            }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error('save failed');
        ctx.payload = data.payload;
        setStatus('Guardado.');
        renderMoreSummary(ctx);
    } catch {
        setStatus('Error al guardar.');
    }
}

function scheduleSave(ctx) {
    setStatus('Cambios pendientes…');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => persist(ctx), 400);
}

function renderMoreSummary(ctx) {
    const m = ctx.payload.metrics;
    const income = document.getElementById('more-income-display');
    const actual = document.getElementById('more-actual');
    const planned = document.getElementById('more-planned');
    const surplus = document.getElementById('more-surplus');
    const savings = document.getElementById('more-savings');

    if (income) income.textContent = formatCurrency(ctx.payload.budget.income);
    if (actual) actual.textContent = formatCurrency(m.actual_expenses);
    if (planned) planned.textContent = formatCurrency(m.planned_expenses);
    if (surplus) surplus.textContent = formatCurrency(m.actual_surplus);
    if (savings) {
        savings.textContent = `${m.actual_savings_rate}%`;
        savings.className =
            m.actual_surplus < 0
                ? 'mt-1 font-semibold tabular-nums text-bad'
                : m.actual_savings_rate >= 20
                  ? 'mt-1 font-semibold tabular-nums text-brand'
                  : 'mt-1 font-semibold tabular-nums';
    }
}

function renderSliders(ctx) {
    const box = document.getElementById('more-sliders');
    if (!box) return;

    box.innerHTML = '';
    ctx.payload.categories.forEach((cat, index) => {
        const open = index < 1;
        let subsHtml = '';
        cat.subcategories.forEach((sub) => {
            subsHtml += `
                <div class="mt-3">
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="text-muted">${sub.name}</span>
                        <span class="tabular-nums font-medium" data-sub-label="${sub.id}">${formatCurrency(sub.amount)}</span>
                    </div>
                    <input type="range" min="0" max="${sub.max}" value="${sub.amount}" data-sub-id="${sub.id}" class="sub-slider w-full">
                </div>`;
        });

        box.insertAdjacentHTML(
            'beforeend',
            `<div class="overflow-hidden rounded-xl border border-border">
                <button type="button" class="accordion flex w-full items-center justify-between bg-surface px-3 py-3 text-left text-sm font-medium ${open ? 'active' : ''}">
                    <span class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:${cat.color}"></span>
                        ${cat.name}
                    </span>
                    <span class="accordion-icon text-muted">${open ? '˄' : '˅'}</span>
                </button>
                <div class="panel px-3 pb-3 ${open ? '' : 'hidden'}">${subsHtml}</div>
            </div>`
        );
    });

    box.querySelectorAll('.accordion').forEach((btn) => {
        btn.addEventListener('click', () => {
            const panel = btn.nextElementSibling;
            const isOpen = !panel.classList.contains('hidden');
            panel.classList.toggle('hidden', isOpen);
            btn.querySelector('.accordion-icon').textContent = isOpen ? '˅' : '˄';
        });
    });

    box.querySelectorAll('.sub-slider').forEach((input) => {
        input.addEventListener('input', () => {
            const subId = Number(input.dataset.subId);
            const value = Number(input.value) || 0;
            ctx.payload.categories.forEach((cat) => {
                cat.subcategories.forEach((sub) => {
                    if (sub.id === subId) sub.amount = value;
                });
                cat.total = cat.subcategories.reduce((sum, s) => sum + Number(s.amount), 0);
                cat.planned_total = cat.total;
            });
            const label = box.querySelector(`[data-sub-label="${subId}"]`);
            if (label) label.textContent = formatCurrency(value);

            const planned = ctx.payload.categories.reduce((sum, c) => sum + Number(c.planned_total || c.total || 0), 0);
            const income = Number(ctx.payload.budget.income) || 0;
            ctx.payload.metrics.planned_expenses = planned;
            ctx.payload.metrics.planned_surplus = income - planned;
            ctx.payload.metrics.planned_savings_rate =
                income > 0 ? Math.round(((income - planned) / income) * 100) : 0;
            renderMoreSummary(ctx);
            scheduleSave(ctx);
        });
    });
}

export function initMore(ctx) {
    document.getElementById('more-month')?.addEventListener('change', (e) => {
        const [y, m] = e.target.value.split('-').map(Number);
        if (y && m) navigateMonth(y, m);
    });

    renderMoreSummary(ctx);
    renderSliders(ctx);

    ctx.onPayloadChange = (payload) => {
        ctx.payload = payload;
        renderMoreSummary(ctx);
        renderSliders(ctx);
    };
}
