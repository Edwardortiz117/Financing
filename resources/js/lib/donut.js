/**
 * SVG donut chart using stroke-dasharray.
 * @param {HTMLElement} container
 * @param {Array<{color: string, value: number}>} slices
 * @param {{size?: number, stroke?: number, centerHtml?: string}} options
 */
export function renderDonut(container, slices, options = {}) {
    if (!container) return;

    const size = options.size ?? 112;
    const stroke = options.stroke ?? 14;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const total = slices.reduce((sum, s) => sum + Math.max(0, Number(s.value) || 0), 0);

    let offset = 0;
    const arcs =
        total <= 0
            ? `<circle class="donut-ring" cx="${size / 2}" cy="${size / 2}" r="${radius}" fill="none" stroke="#e5e5e5" stroke-width="${stroke}"></circle>`
            : slices
                  .filter((s) => s.value > 0)
                  .map((s) => {
                      const len = (s.value / total) * circumference;
                      const circle = `<circle class="donut-ring" cx="${size / 2}" cy="${size / 2}" r="${radius}" fill="none" stroke="${s.color}" stroke-width="${stroke}" stroke-dasharray="${len} ${circumference - len}" stroke-dashoffset="${-offset}" stroke-linecap="butt"></circle>`;
                      offset += len;
                      return circle;
                  })
                  .join('');

    container.innerHTML = `
        <div class="relative" style="width:${size}px;height:${size}px">
            <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" aria-hidden="true">${arcs}</svg>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-2 text-center">
                ${options.centerHtml || ''}
            </div>
        </div>`;
}
