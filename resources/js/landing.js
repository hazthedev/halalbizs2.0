import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { CustomEase } from 'gsap/CustomEase';

gsap.registerPlugin(ScrollTrigger, CustomEase);

/**
 * Landing page (/welcome) motion layer — GSAP only, loaded on this one page
 * (see the @push('scripts') in resources/views/livewire/storefront/landing.blade.php).
 * Everything here is progressive enhancement over server-rendered, already-visible
 * markup: reduced motion (or GSAP failing to load) leaves the page exactly as
 * Blade rendered it, nothing hides behind a CSS class that requires JS to lift.
 */

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// ── House motion tokens ──
// Read straight off resources/css/app.css's @theme custom properties instead
// of hard-coding a second set of curves/durations, so this stays one motion
// system with the CSS-driven `.reveal` / `.motion-reveal` utilities used
// everywhere else on the site. Falls back to close built-in equivalents if a
// token is ever renamed/removed.
let ENTER_EASE = 'power2.out';
let STD_EASE = 'power1.out';
let DUR_REVEAL = 0.55;
let DUR_STD = 0.3;
let tokensLoaded = false;

function loadHouseTokens() {
    if (tokensLoaded) return;
    tokensLoaded = true;

    const css = getComputedStyle(document.documentElement);
    const seconds = (name, fallback) => {
        const n = parseFloat(css.getPropertyValue(name));
        return Number.isFinite(n) ? n / 1000 : fallback;
    };
    const bezier = (name, fallback) => {
        const raw = css.getPropertyValue(name).trim();
        const match = raw.match(/cubic-bezier\(([^)]+)\)/);
        return match ? match[1].replace(/\s+/g, '') : fallback;
    };

    DUR_REVEAL = seconds('--dur-reveal', 0.55);
    DUR_STD = seconds('--dur-standard', 0.3);

    try {
        CustomEase.create('hb-ease-out-soft', bezier('--ease-out-soft', '.22,1,.36,1'));
        CustomEase.create('hb-ease-standard', bezier('--ease-standard', '.4,0,.2,1'));
        ENTER_EASE = 'hb-ease-out-soft';
        STD_EASE = 'hb-ease-standard';
    } catch {
        // Keep the power2.out/power1.out fallbacks above — close enough visually,
        // and never worth breaking the page over.
    }
}

// Mirrors PHP's number_format($n) with its default args (0 decimals, ','
// thousands separator) — the exact formatting stats.blade.php used to render
// the server value, so the count-up always lands on an identical string.
function formatCount(n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

let ctx = null;

function cleanup() {
    // gsap.context() tracks every tween/timeline/ScrollTrigger created inside
    // it; revert() kills them all and strips any inline styles GSAP applied,
    // so wrappers return to their normal stylesheet-computed state. Nothing
    // is left "held" for a position:fixed descendant to get trapped by.
    ctx?.revert();
    ctx = null;
}

function heroEntrance(hero) {
    const mark = hero.querySelector('[data-motion="ornament"]');
    const eyebrow = hero.querySelector('[data-motion="eyebrow"]');
    const subcopy = hero.querySelector('[data-motion="subcopy"]');
    const ctaRow = hero.querySelector('[data-motion="cta-row"]');

    // Headline stays on the pure-CSS `.reveal` utility (T1's contract) — it is
    // never referenced here, so the page stays readable with zero JS. Everything
    // below is a `.from()` tween: GSAP only ever *adds* the hidden starting
    // state at the instant it runs, so if the script never executes at all
    // (blocked, errors before this point), these elements are simply never
    // touched and stay at their normal, visible, server-rendered state.
    const tl = gsap.timeline({ defaults: { ease: ENTER_EASE, clearProps: 'all' } });
    if (mark) tl.from(mark, { opacity: 0, scale: 0.5, rotate: -25, duration: DUR_STD }, 0);
    if (eyebrow) tl.from(eyebrow, { opacity: 0, y: 14, duration: DUR_REVEAL }, 0.05);
    if (subcopy) tl.from(subcopy, { opacity: 0, y: 16, duration: DUR_REVEAL }, 0.16);
    if (ctaRow) tl.from(ctaRow, { opacity: 0, y: 16, duration: DUR_REVEAL }, 0.28);
}

/**
 * Scroll-triggered reveal for one section. `itemsSelector` is scoped inside
 * `sectionSelector`; pass null to animate the section-selector match itself
 * (used for single-block bands like seller-cta/footer-cta).
 */
function scrollReveal(sectionSelector, itemsSelector, stagger = 0.08) {
    const section = document.querySelector(sectionSelector);
    if (!section) return;
    const targets = itemsSelector ? section.querySelectorAll(itemsSelector) : [section];
    if (!targets.length) return;

    gsap.from(targets, {
        opacity: 0,
        y: 24,
        duration: DUR_REVEAL,
        ease: ENTER_EASE,
        stagger,
        clearProps: 'all',
        scrollTrigger: {
            trigger: section,
            start: 'top 82%',
            once: true,
        },
    });
}

function scrollReveals() {
    scrollReveal('[data-land="trust"]', '[data-motion="item"]', 0.1);
    scrollReveal('[data-land="categories"]', '[data-motion="item"]', 0.05);
    // "Sequential" — a longer stagger reads as one-step-after-another rather
    // than a grid popping in together.
    scrollReveal('[data-land="how"]', '[data-motion="item"]', 0.18);
    scrollReveal('[data-land="seller"] > div', null);
    scrollReveal('[data-land="footer-cta"] > div', null);
}

/** Stat count-ups — ends exactly on the server-rendered value/formatting. */
function countUps() {
    const stats = document.querySelector('[data-land="stats"]');
    if (!stats) return;
    const nodes = stats.querySelectorAll('[data-countup]');
    if (!nodes.length) return;

    ScrollTrigger.create({
        trigger: stats,
        start: 'top 85%',
        once: true,
        onEnter: () => {
            nodes.forEach((el) => {
                const target = parseInt(el.getAttribute('data-target'), 10) || 0;
                if (target <= 0) {
                    el.textContent = formatCount(target);
                    return;
                }
                const proxy = { val: 0 };
                gsap.to(proxy, {
                    val: target,
                    duration: 1.3,
                    ease: STD_EASE,
                    onUpdate: () => {
                        el.textContent = formatCount(proxy.val);
                    },
                    onComplete: () => {
                        // Belt-and-braces: guarantees an exact match with the
                        // server string regardless of any float rounding above.
                        el.textContent = formatCount(target);
                    },
                });
            });
        },
    });
}

/**
 * Subtle parallax on the hero/footer-cta girih ornament. T1's DOM has no
 * separate ornament layer — the `surface-girih` pattern is a background-image
 * on the section root itself — so rather than adding structural markup outside
 * this task's declared surface (data-attrs + script loading only), the girih
 * tile is nudged via `backgroundPositionY`, never `transform`. That keeps the
 * house's hard rule intact by construction: this can never leave a transform
 * on a section wrapper, because it doesn't use transform at all. Tiled
 * background + scrub means it shifts seamlessly with no edge artifacts.
 */
function parallax() {
    ['hero', 'footer-cta'].forEach((key) => {
        const el = document.querySelector(`[data-land="${key}"]`);
        if (!el) return;
        if (getComputedStyle(el).backgroundImage === 'none') return;

        gsap.to(el, {
            backgroundPositionY: '+=28',
            ease: 'none',
            scrollTrigger: {
                trigger: el,
                start: 'top bottom',
                end: 'bottom top',
                scrub: 0.6,
            },
        });
    });
}

function init() {
    // Always tear down first: wire:navigate can land back on /welcome after
    // visiting other pages, and this module (loaded once, live for the whole
    // SPA-style session) must not accumulate duplicate ScrollTriggers.
    cleanup();

    if (!document.querySelector('[data-land]')) return; // not the landing page
    if (prefersReducedMotion()) return; // everything stays exactly as rendered

    loadHouseTokens();

    ctx = gsap.context(() => {
        const hero = document.querySelector('[data-land="hero"]');
        if (hero) heroEntrance(hero);
        scrollReveals();
        countUps();
        parallax();
    });

    // Re-measure trigger positions against the freshly (re)morphed DOM.
    ScrollTrigger.refresh();
}

document.addEventListener('livewire:navigated', init);
init();
