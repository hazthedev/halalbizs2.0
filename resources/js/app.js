import Swiper from 'swiper';
import { Navigation, Pagination, A11y } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination, A11y };

document.addEventListener('alpine:init', () => {
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
