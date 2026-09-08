import { renderDonut } from '../lib/donut.js';
import { formatCurrency, formatDateShort } from '../lib/utils.js';
import { openInvoiceModal } from './actions.js';

function topCategories(payload, limit = 3) {
    return [...payload.categories]
        .filter((c) => c.actual_total > 0)
        .sort((a, b) => b.actual_total - a.actual_total)
        .slice(0, limit);
}

function renderMomBadge(payload) {
    const badge = document.getElementById('home-mom-badge');
    const value = document.getElementById('home-mom-value');
    if (!badge || !value) return;

    const pct = payload.metrics.month_over_month_pct;
    if (pct == null) {
        badge.classList.add('hidden');
        return;
    }

    badge.classList.remove('hidden');
    const sign = pct > 0 ? '+' : '';
    value.textContent = `${sign}${pct}%`;
    badge.className =
        pct > 0
            ? 'mt-3 inline-flex items-center gap-1 rounded-full bg-[#5c3a32] px-2.5 py-1 text-xs text-[#f3b8a8]'
            : 'mt-3 inline-flex items-center gap-1 rounded-full bg-[#1f3d2a] px-2.5 py-1 text-xs text-[#9ae6b4]';
}

function renderCategoryPreview(payload) {
    const list = document.getElementById('home-category-list');
    const donut = document.getElementById('home-donut');
    if (!list) return;

    const top = topCategories(payload, 3);
    if (!top.length) {
        list.innerHTML = '<p class="text-sm text-muted">Aún no hay gastos este mes.</p>';
        renderDonut(donut, [], { size: 96, stroke: 12, centerHtml: '<span class="text-xs text-muted">0%</span>' });
        return;
    }

    list.innerHTML = top
        .map(
            (cat) => `
        <div class="flex items-start justify-between gap-2 text-sm">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background-color:${cat.color}"></span>
                    <span class="truncate font-medium">${cat.name}</span>
                </div>
                <p class="mt-0.5 pl-4 text-xs text-muted">${cat.percent_of_actual}%</p>
            </div>
            <span class="shrink-0 tabular-nums font-medium">${formatCurrency(cat.actual_total)}</span>
        </div>`
        )
        .join('');

    renderDonut(
        donut,
        top.map((c) => ({ color: c.color, value: c.actual_total })),
        {
            size: 96,
            stroke: 12,
            centerHtml: `<span class="text-[11px] font-semibold text-ink">${payload.metrics.categorized_pct}%</span>`,
        }
    );
}

function renderRecent(payload) {
    const list = document.getElementById('home-recent-list');
    if (!list) return;

    const recent = [...payload.transactions].slice(0, 5);
    if (!recent.length) {
        list.innerHTML = '<p class="rounded-2xl bg-card px-4 py-5 text-sm text-muted shadow-sm ring-1 ring-border">No hay gastos recientes.</p>';
        return;
    }

    list.innerHTML = recent
        .map((tx) => {
            const title = tx.note || tx.subcategory_name || 'Factura';
            const tag = tx.category_name || 'Gasto';
            const color = tx.category_color || '#22c55e';
            return `
            <article class="flex items-center gap-3 rounded-2xl bg-card px-3 py-3 shadow-sm ring-1 ring-border">
                <button type="button" class="home-tx-thumb flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface text-[10px] text-muted"
                    ${tx.invoice_url ? `data-invoice-url="${tx.invoice_url}" data-invoice-title="${title}" data-invoice-pdf="${tx.invoice_is_pdf ? '1' : '0'}"` : ''}>
                    ${
                        tx.invoice_url
                            ? tx.invoice_is_pdf
                                ? 'PDF'
                                : `<img src="${tx.invoice_url}" alt="" class="h-full w-full object-cover">`
                            : `<span class="inline-block h-3 w-3 rounded-sm" style="background:${color}"></span>`
                    }
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-medium text-ink">${title}</p>
                        <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium text-white" style="background-color:${color}">${tag}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted">${formatDateShort(tx.occurred_on)}</p>
                </div>
                <p class="shrink-0 text-sm font-semibold tabular-nums">${formatCurrency(tx.amount)}</p>
            </article>`;
        })
        .join('');

    list.querySelectorAll('[data-invoice-url]').forEach((btn) => {
        btn.addEventListener('click', () => {
            openInvoiceModal(btn.dataset.invoiceUrl, btn.dataset.invoiceTitle || 'Factura');
        });
    });
}

export function renderHome(ctx) {
    const total = document.getElementById('home-month-total');
    if (total) total.textContent = formatCurrency(ctx.payload.metrics.actual_expenses);
    renderMomBadge(ctx.payload);
    renderCategoryPreview(ctx.payload);
    renderRecent(ctx.payload);
}

export function initHome(ctx) {
    renderHome(ctx);
    ctx.onPayloadChange = (payload) => {
        ctx.payload = payload;
        renderHome(ctx);
    };
}
