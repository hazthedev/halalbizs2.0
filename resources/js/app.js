import Swiper from 'swiper';
import { Navigation, Pagination, A11y } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import ApexCharts from 'apexcharts';
import './fly-to-cart';

window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination, A11y };
window.ApexCharts = ApexCharts;

// ===== Souk (Emerald & Brass) chart palette — solid fills, warm neutrals =====
// Emerald/warn/danger hexes are kept exact (dashboard tests + status semantics
// assert them); only the neutrals are warmed to match the new ivory canvas.
const HB_INK = '#1A1714';
const HB_INK_SOFT = '#5C544B';
const HB_LINE = '#E7E1D5';
const HB_EMERALD = '#047857';
const HB_WARN = '#B45309';
const HB_DANGER = '#BE123C';
const HB_SLATE = '#7C6F5A';
const HB_BRASS = '#A8772E';

// Status → token colour, shared by donuts across dashboards.
window.hbStatusColor = (status) => ({
    pending_payment: HB_WARN,
    confirmed: HB_INK,
    processing: HB_INK,
    shipped: HB_SLATE,
    delivered: HB_SLATE,
    completed: HB_EMERALD,
    cancelled: HB_DANGER,
    return_requested: HB_DANGER,
    returned: HB_DANGER,
    refunded: HB_DANGER,
}[status] ?? HB_INK_SOFT);

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * Base ApexCharts options shared by every HalalBizs chart: flat, solid fills,
 * Plus Jakarta Sans, tabular tooltips, no toolbar. Merged shallowly with the
 * per-chart payload's `options`.
 */
function hbBaseOptions(type) {
    return {
        chart: {
            type,
            fontFamily: '"Plus Jakarta Sans Variable", ui-sans-serif, system-ui, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: !prefersReducedMotion(), speed: 400 },
            background: 'transparent',
        },
        colors: [HB_EMERALD, HB_BRASS, HB_SLATE, HB_WARN, HB_DANGER, HB_INK_SOFT],
        fill: { type: 'solid', opacity: type === 'area' ? 0.08 : 1 },
        stroke: { curve: 'smooth', width: type === 'line' || type === 'area' ? 2.5 : 0 },
        grid: { borderColor: HB_LINE, strokeDashArray: 0, padding: { left: 8, right: 8 } },
        dataLabels: { enabled: false },
        tooltip: { style: { fontSize: '13px' } },
        legend: { fontSize: '13px', labels: { colors: HB_INK_SOFT }, markers: { radius: 12 } },
        xaxis: { labels: { style: { colors: HB_INK_SOFT, fontSize: '12px' } }, axisBorder: { color: HB_LINE }, axisTicks: { color: HB_LINE } },
        yaxis: { labels: { style: { colors: HB_INK_SOFT, fontSize: '12px' } } },
    };
}

function deepMerge(base, extra) {
    const out = { ...base };
    for (const key of Object.keys(extra ?? {})) {
        out[key] = extra[key] && typeof extra[key] === 'object' && !Array.isArray(extra[key])
            ? deepMerge(base[key] ?? {}, extra[key])
            : extra[key];
    }
    return out;
}

document.addEventListener('alpine:init', () => {
    /**
     * <x-ui.chart> driver. `payload` = { type, series, options }. When the
     * dashboard recomputes (period change) it dispatches `refreshEvent` with a
     * fresh payload; we update in place so there's no re-init flicker. The host
     * div is wire:ignore so Livewire morphs leave the chart alone.
     */
    window.Alpine.data('hbChart', (payload, refreshEvent = null) => ({
        chart: null,
        init() {
            const opts = deepMerge(hbBaseOptions(payload.type), payload.options ?? {});
            opts.series = payload.series;
            if (payload.labels) opts.labels = payload.labels;
            // Money charts pass `money: true` and ringgit-valued series; format
            // the axis + tooltip as RM (PHP can't ship JS formatter functions).
            if (payload.money) {
                const rm = (v) => 'RM ' + Math.round(Number(v) || 0).toLocaleString('en-MY');
                opts.yaxis = deepMerge(opts.yaxis ?? {}, { labels: { formatter: rm } });
                opts.tooltip = deepMerge(opts.tooltip ?? {}, { y: { formatter: rm } });
            }
            this.chart = new window.ApexCharts(this.$refs.canvas, opts);
            this.chart.render();

            if (refreshEvent) {
                this.$wire.on(refreshEvent, (fresh) => {
                    const next = Array.isArray(fresh) ? fresh[0] : fresh;
                    this.chart.updateOptions({
                        series: next.series,
                        ...(next.labels ? { labels: next.labels } : {}),
                        ...(next.options ?? {}),
                    });
                });
            }
        },
        destroy() {
            this.chart?.destroy();
        },
    }));

    // Count-up for stat cards (shared; honours reduced motion).
    window.Alpine.data('countUp', (target, duration = 400) => ({
        display: 0,
        init() {
            if (prefersReducedMotion() || target <= 0) {
                this.display = target;
                return;
            }
            const start = performance.now();
            const tick = (now) => {
                const p = Math.min(1, (now - start) / duration);
                this.display = Math.round(target * p);
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        },
    }));

    // Header cart badge — optimistic bump, server reconciles via `cart-updated`.
    window.Alpine.store('cart', {
        count: 0,
        pulse: false,
        set(n) {
            this.count = n;
        },
        bump(n = 1) {
            this.count += n;
            this.pulse = true;
            setTimeout(() => (this.pulse = false), 300);
        },
    });

    // Toast store — ink surface, 4s auto-dismiss, optional action slot (design §5).
    window.Alpine.store('toasts', {
        items: [],
        counter: 0,

        push(message, options = {}) {
            const id = ++this.counter;
            const toast = {
                id,
                message,
                type: options.type ?? 'success',
                actionLabel: options.actionLabel ?? null,
                actionEvent: options.actionEvent ?? null,
                actionPayload: options.actionPayload ?? null,
            };
            this.items.push(toast);
            setTimeout(() => this.dismiss(id), options.duration ?? 4000);
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    // Recently-viewed product ids in localStorage (docs/05 B1/B4) — newest first, max 12.
    window.recentlyViewed = {
        key: 'recently_viewed',
        all() {
            try {
                return JSON.parse(localStorage.getItem(this.key) ?? '[]');
            } catch {
                return [];
            }
        },
        push(id) {
            const ids = this.all().filter((x) => x !== id);
            ids.unshift(id);
            localStorage.setItem(this.key, JSON.stringify(ids.slice(0, 12)));
        },
    };

    // Recent search terms in localStorage — max 8.
    window.recentSearches = {
        key: 'recent_searches',
        all() {
            try {
                return JSON.parse(localStorage.getItem(this.key) ?? '[]');
            } catch {
                return [];
            }
        },
        push(term) {
            if (!term) return;
            const terms = this.all().filter((t) => t !== term);
            terms.unshift(term);
            localStorage.setItem(this.key, JSON.stringify(terms.slice(0, 8)));
        },
    };
});

// Livewire dispatches `toast` browser events from the server.
window.addEventListener('toast', (event) => {
    window.Alpine.store('toasts').push(event.detail.message, event.detail);
});

window.addEventListener('cart-updated', (event) => {
    window.Alpine.store('cart').set(event.detail.count);
});
