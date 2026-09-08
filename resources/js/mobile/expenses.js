import { renderDonut } from '../lib/donut.js';
import {
    csrfToken,
    formatCurrency,
    formatDateShort,
    monthLabel,
    navigateMonth,
    setStatus,
} from '../lib/utils.js';
import { openInvoiceModal } from './actions.js';

let legendExpanded = false;

function sortedCategories(payload, sort) {
    const list = [...payload.categories];
    if (sort === 'name') {
        return list.sort((a, b) => a.name.localeCompare(b.name, 'es'));
    }
    if (sort === 'date') {
        return list.sort((a, b) => b.invoice_count - a.invoice_count);
    }
    return list.sort((a, b) => b.actual_total - a.actual_total);
}

function sortedTransactions(payload, sort) {
    const list = [...payload.transactions];
    if (sort === 'name') {
        return list.sort((a, b) =>
            String(a.subcategory_name || '').localeCompare(String(b.subcategory_name || ''), 'es')
        );
    }
    if (sort === 'date') {
        return list.sort((a, b) => String(b.occurred_on).localeCompare(String(a.occurred_on)));
    }
    return list.sort((a, b) => b.amount - a.amount);
}

function renderFacturas(ctx, sort) {
    const box = document.getElementById('expenses-facturas');
    if (!box) return;

    const txs = sortedTransactions(ctx.payload, sort);
    if (!txs.length) {
        box.innerHTML =
            '<p class="rounded-2xl bg-card px-4 py-6 text-center text-sm text-muted shadow-sm ring-1 ring-border">No hay facturas este mes.</p>';
        return;
    }

    box.innerHTML = txs
        .map((tx) => {
            const title = tx.note || tx.subcategory_name || 'Factura';
            const color = tx.category_color || '#8a8a8a';
            return `
            <article class="flex items-start gap-3 rounded-2xl bg-card p-3 shadow-sm ring-1 ring-border">
                <button type="button" class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface text-[10px] text-muted"
                    ${tx.invoice_url ? `data-invoice-url="${tx.invoice_url}" data-invoice-title="${title}"` : 'disabled'}>
                    ${
                        tx.invoice_url
                            ? tx.invoice_is_pdf
                                ? 'PDF'
                                : `<img src="${tx.invoice_url}" alt="" class="h-full w-full object-cover">`
                            : `<span class="h-3 w-3 rounded-sm" style="background:${color}"></span>`
                    }
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-medium">${title}</p>
                        <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium text-white" style="background:${color}">${tx.category_name || 'Gasto'}</span>
                    </div>
                    <p class="mt-1 text-xs text-muted">${formatDateShort(tx.occurred_on)} · ${tx.subcategory_name || ''}</p>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="font-semibold tabular-nums">${formatCurrency(tx.amount)}</span>
                        <button type="button" data-tx-id="${tx.id}" class="tx-delete text-xs text-bad">Eliminar</button>
                    </div>
                </div>
            </article>`;
        })
        .join('');

    box.querySelectorAll('[data-invoice-url]').forEach((btn) => {
        btn.addEventListener('click', () => openInvoiceModal(btn.dataset.invoiceUrl, btn.dataset.invoiceTitle));
    });

    box.querySelectorAll('.tx-delete').forEach((btn) => {
        btn.addEventListener('click', async () => {
            setStatus('Eliminando…');
            const res = await fetch(`/transactions/${btn.dataset.txId}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (data.ok) {
                ctx.payload = data.payload;
                setStatus('Gasto eliminado.');
                renderExpenses(ctx);
            } else {
                setStatus('No se pudo eliminar.');
            }
        });
    });
}

function renderCategorias(ctx, sort) {
    const totalEl = document.getElementById('expenses-total');
    const legend = document.getElementById('expenses-legend');
    const donut = document.getElementById('expenses-donut');
    const cards = document.getElementById('expenses-category-cards');
    const uncat = document.getElementById('expenses-uncategorized');
    if (!legend || !cards) return;

    const actual = ctx.payload.metrics.actual_expenses;
    if (totalEl) totalEl.textContent = formatCurrency(actual);

    const cats = sortedCategories(ctx.payload, sort).filter((c) => c.actual_total > 0 || c.planned_total > 0);
    const legendCats = legendExpanded ? cats : cats.slice(0, 5);

    const legendRows = [];
    if (ctx.payload.metrics.uncategorized_amount > 0) {
        legendRows.push({
            name: 'Sin categorizar',
            color: '#c4c4c4',
            pct: actual > 0 ? Math.round((ctx.payload.metrics.uncategorized_amount / actual) * 100) : 0,
        });
    }
    legendCats.forEach((c) => {
        legendRows.push({ name: c.name, color: c.color, pct: c.percent_of_actual });
    });

    legend.innerHTML = legendRows
        .map(
            (row) => `
        <div class="flex items-center gap-2 text-xs">
            <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:${row.color}"></span>
            <span class="min-w-0 flex-1 truncate text-muted">${row.name}</span>
            <span class="tabular-nums text-ink">${row.pct}%</span>
        </div>`
        )
        .join('');

    const donutSlices = [
        ...(ctx.payload.metrics.uncategorized_amount > 0
            ? [{ color: '#c4c4c4', value: ctx.payload.metrics.uncategorized_amount }]
            : []),
        ...cats.map((c) => ({ color: c.color, value: c.actual_total })),
    ];

    renderDonut(donut, donutSlices, {
        size: 120,
        stroke: 16,
        centerHtml: `<span class="text-sm font-semibold text-ink">${ctx.payload.metrics.categorized_pct}%</span><span class="text-[10px] text-muted">Categorizados</span>`,
    });

    if (uncat) {
        const amount = ctx.payload.metrics.uncategorized_amount;
        const count = ctx.payload.metrics.uncategorized_count;
        if (amount > 0) {
            uncat.classList.remove('hidden');
            uncat.innerHTML = `<p class="font-semibold">Sin categorizar · ${formatCurrency(amount)}</p>
                <p class="mt-1">¡Tienes ${count} factura${count === 1 ? '' : 's'} suelta${count === 1 ? '' : 's'}! Categoriza tus facturas y mantén el control de tus gastos.</p>`;
        } else {
            uncat.classList.add('hidden');
            uncat.innerHTML = '';
        }
    }

    const cardCats = sortedCategories(ctx.payload, sort).filter((c) => c.actual_total > 0 || c.invoice_count > 0);
    if (!cardCats.length) {
        cards.innerHTML =
            '<p class="rounded-2xl bg-card px-4 py-6 text-center text-sm text-muted shadow-sm ring-1 ring-border">Sin gastos por categoría este mes.</p>';
        return;
    }

    cards.innerHTML = cardCats
        .map((cat) => {
            const usage = cat.budget_usage_pct || 0;
            const over = usage > 100;
            const barWidth = Math.min(usage, 100);
            const barColor = over ? '#ef4444' : cat.color;
            return `
            <article class="rounded-[22px] bg-card p-4 shadow-sm ring-1 ring-border">
                <div class="flex items-start gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-surface">
                        <span class="inline-block h-4 w-4 rounded-sm" style="background:${cat.color}"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <h3 class="truncate font-semibold">${cat.name}</h3>
                                <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:${cat.color}"></span>
                            </div>
                            <p class="shrink-0 text-lg font-semibold tabular-nums">${formatCurrency(cat.actual_total)}</p>
                        </div>
                        <p class="mt-1 text-xs text-muted">Facturas: ${cat.invoice_count} · Presupuesto: ${formatCurrency(cat.planned_total)}</p>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="h-3 overflow-hidden rounded-full bg-surface">
                        <div class="h-full rounded-full" style="width:${barWidth}%;background:${barColor}"></div>
                    </div>
                    <p class="mt-1 text-right text-xs font-semibold ${over ? 'text-bad' : 'text-muted'}">${usage}%</p>
                </div>
            </article>`;
        })
        .join('');
}

export function renderExpenses(ctx) {
    const sort = document.getElementById('expenses-sort')?.value || 'amount';
    const tab = ctx.tab || 'categorias';
    if (tab === 'facturas') renderFacturas(ctx, sort);
    else renderCategorias(ctx, sort);
}

export function initExpenses(ctx) {
    const monthInput = document.getElementById('expenses-month');
    monthInput?.addEventListener('change', (e) => {
        const [y, m] = e.target.value.split('-').map(Number);
        if (y && m) navigateMonth(y, m, { tab: ctx.tab });
    });

    document.getElementById('expenses-sort')?.addEventListener('change', () => renderExpenses(ctx));

    document.getElementById('expenses-legend-more')?.addEventListener('click', () => {
        legendExpanded = !legendExpanded;
        const btn = document.getElementById('expenses-legend-more');
        if (btn) {
            btn.innerHTML = legendExpanded
                ? 'Ver menos <svg class="h-4 w-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m18 15-6-6-6 6"/></svg>'
                : 'Ver más <svg class="h-4 w-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>';
        }
        renderExpenses(ctx);
    });

    // Label hint on filter
    if (monthInput) {
        const label = monthInput.closest('label');
        if (label) {
            const hint = label.querySelector('span');
            if (hint) hint.textContent = `Filtrar por ${monthLabel(ctx.year, ctx.month)}`;
        }
    }

    renderExpenses(ctx);
    ctx.onPayloadChange = (payload) => {
        ctx.payload = payload;
        renderExpenses(ctx);
    };
}
