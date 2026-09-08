import { initActions } from './mobile/actions.js';
import { initExpenses } from './mobile/expenses.js';
import { initHome } from './mobile/home.js';
import { initMore } from './mobile/more.js';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('mobile-app');
    if (!root) return;

    const ctx = {
        page: root.dataset.page,
        tab: root.dataset.tab || 'categorias',
        payload: JSON.parse(root.dataset.payload),
        updateUrl: root.dataset.updateUrl,
        storeTxUrl: root.dataset.storeTxUrl,
        parseInvoiceUrl: root.dataset.parseInvoiceUrl,
        year: Number(root.dataset.year),
        month: Number(root.dataset.month),
        onPayloadChange: null,
    };

    initActions(ctx);

    if (ctx.page === 'home') initHome(ctx);
    if (ctx.page === 'expenses') initExpenses(ctx);
    if (ctx.page === 'more') initMore(ctx);
});
