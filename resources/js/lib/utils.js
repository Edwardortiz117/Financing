export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function formatCurrency(num) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
    }).format(Number(num) || 0);
}

export function formatDateShort(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T12:00:00`);
    const days = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
    const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return `${days[d.getDay()]}, ${String(d.getDate()).padStart(2, '0')}/${months[d.getMonth()]}/${d.getFullYear()}`;
}

export function monthLabel(year, month) {
    const months = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];
    return `${months[month - 1] || ''} ${year}`;
}

export function navigateMonth(year, month, extra = {}) {
    const url = new URL(window.location.href);
    url.searchParams.set('year', String(year));
    url.searchParams.set('month', String(month));
    Object.entries(extra).forEach(([k, v]) => {
        if (v == null || v === '') url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
    });
    window.location.href = url.pathname + '?' + url.searchParams.toString();
}

export function defaultDateInMonth(year, month) {
    const today = new Date();
    if (today.getFullYear() === year && today.getMonth() + 1 === month) {
        return today.toISOString().slice(0, 10);
    }
    return `${year}-${String(month).padStart(2, '0')}-01`;
}

export function isPdfFile(fileOrUrl, isPdfFlag) {
    if (typeof isPdfFlag === 'boolean') return isPdfFlag;
    if (fileOrUrl instanceof File) {
        return fileOrUrl.type === 'application/pdf' || /\.pdf$/i.test(fileOrUrl.name);
    }
    return /\.pdf($|\?)/i.test(String(fileOrUrl || ''));
}

export function setStatus(text) {
    const el = document.getElementById('save-status');
    if (el) el.textContent = text || '';
}

export function fillSubcategorySelect(select, categories) {
    if (!select) return;
    select.innerHTML = '';
    categories.forEach((cat) => {
        cat.subcategories.forEach((sub) => {
            const opt = document.createElement('option');
            opt.value = String(sub.id);
            opt.textContent = `${cat.name} — ${sub.name}`;
            select.appendChild(opt);
        });
    });
}

export function allocationsFromPayload(payload) {
    const allocations = [];
    payload.categories.forEach((cat) => {
        cat.subcategories.forEach((sub) => {
            allocations.push({ subcategory_id: sub.id, amount: Number(sub.amount) || 0 });
        });
    });
    return allocations;
}
