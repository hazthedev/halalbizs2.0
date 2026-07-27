import { animate, createTimeline, createScope, onScroll, stagger, svg, cubicBezier } from 'animejs';

/**
 * Landing page (/welcome) motion layer — anime.js v4 only, loaded on this one
 * page (see the @push('scripts') in
 * resources/views/livewire/storefront/landing.blade.php).
 * Everything here is progressive enhancement over server-rendered, already-
 * visible markup: every tween below only ever *adds* its hidden/offset
 * starting value at the moment it is created ([from, to] keyframes) — if
 * this script never executes at all (blocked, errors before that point), the
 * elements are simply never touched and stay at their normal, visible,
 * server-rendered state. Nothing hides behind a CSS class that requires JS
 * to lift.
 *
 * prefers-reduced-motion is REDUCE, not remove (2026-07-24; second "no
 * animation" report from a Windows machine with Accessibility → Animation
 * effects switched off): scroll-linked parallax, the SVG draw, and the
 * count-ups are dropped entirely — that class of motion is what the setting
 * exists for — while the entrance and section reveals fall back to short
 * opacity-only crossfades (no translate/scale), which vestibular-safety
 * guidance permits. A reduced-motion visitor still sees the page respond;
 * a no-JS visitor still sees everything, statically.
 */

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// ── House motion tokens ──
// Read straight off resources/css/app.css's @theme custom properties instead
// of hard-coding a second set of curves/durations, so this stays one motion
// system with the CSS-driven `.reveal` / `.motion-reveal` utilities used
// everywhere else on the site. anime.js v4 removed the `"cubicBezier(...)"`
// ease STRING syntax (it warns and falls back to linear) — the house bezier
// curves have to be built with the imported `cubicBezier()` function instead
// and passed as an actual easing function. Falls back to close built-in
// named eases if a token is ever renamed/removed.
let ENTER_EASE = 'outQuint';
let STD_EASE = 'outQuad';
let DUR_REVEAL = 550; // ms — anime.js v4 durations are milliseconds, not seconds
let DUR_STD = 300; // ms
let tokensLoaded = false;

function loadHouseTokens() {
    if (tokensLoaded) return;
    tokensLoaded = true;

    const css = getComputedStyle(document.documentElement);
    const millis = (name, fallback) => {
        const n = parseFloat(css.getPropertyValue(name));
        return Number.isFinite(n) ? n : fallback;
    };
    const bezierNums = (name, fallback) => {
        const raw = css.getPropertyValue(name).trim();
        const match = raw.match(/cubic-bezier\(([^)]+)\)/);
        const source = match ? match[1] : fallback;
        const nums = source.split(',').map((n) => parseFloat(n));
        return nums.length === 4 && nums.every(Number.isFinite) ? nums : null;
    };

    DUR_REVEAL = millis('--dur-reveal', 550);
    DUR_STD = millis('--dur-standard', 300);

    const outSoft = bezierNums('--ease-out-soft', '.22,1,.36,1');
    const standard = bezierNums('--ease-standard', '.4,0,.2,1');
    ENTER_EASE = outSoft ? cubicBezier(...outSoft) : 'outQuint';
    STD_EASE = standard ? cubicBezier(...standard) : 'outQuad';
}

// Mirrors PHP's number_format($n) with its default args (0 decimals, ','
// thousands separator) — the exact formatting stats.blade.php used to render
// the server value, so the count-up always lands on an identical string.
function formatCount(n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

let scope = null;
let REDUCED = false; // set per-init from prefers-reduced-motion

function cleanup() {
    // A Scope tracks every animation/timeline/scroll-observer created while
    // it is `.current` (see createScope().add(fn) below); revert() kills them
    // all and restores the DOM properties they touched to their pre-tween
    // values. Nothing is left "held" for a position:fixed descendant to get
    // trapped by, and re-running init() never accumulates duplicate
    // scroll observers.
    scope?.revert();
    scope = null;
}

function heroEntrance(hero) {
    const eyebrow = hero.querySelector('[data-motion="eyebrow"]');
    const words = hero.querySelectorAll('[data-motion="word"]');
    const subcopy = hero.querySelector('[data-motion="subcopy"]');
    const ctaRow = hero.querySelector('[data-motion="cta-row"]');

    // Reduced motion: same choreography, opacity-only and quicker — the
    // rises/overshoot go, the crossfade sequencing stays.
    const dur = REDUCED ? DUR_STD : DUR_REVEAL;
    const tl = createTimeline({ defaults: { ease: ENTER_EASE } });
    if (eyebrow) {
        tl.add(eyebrow, { opacity: [0, 1], ...(REDUCED ? {} : { translateY: [26, 0] }), duration: dur }, 0);
    }
    if (words.length) {
        // Slight overshoot on the headline only — `outBack` with a modest
        // magnitude, not the house's soft ease — so the word-by-word rise
        // reads with a touch more character than the rest of the hero.
        tl.add(words, {
            opacity: [0, 1],
            ...(REDUCED ? { ease: STD_EASE } : { translateY: [34, 0], ease: 'outBack(1.2)' }),
            duration: dur,
            delay: stagger(REDUCED ? 40 : 70),
        }, 90);
    }
    if (subcopy) {
        // Blur-in (React Bits "Blur Text", ported): sharpens as it rises.
        // Filter is dropped under reduced motion along with the rise.
        tl.add(subcopy, {
            opacity: [0, 1],
            ...(REDUCED ? {} : { translateY: [28, 0], filter: ['blur(8px)', 'blur(0px)'] }),
            duration: dur,
        }, 320);
    }
    if (ctaRow) {
        tl.add(ctaRow, { opacity: [0, 1], ...(REDUCED ? {} : { translateY: [26, 0], scale: [0.97, 1] }), duration: dur }, 480);
    }
}

// ── Parallax ──
// Every [data-plx] element gets an *additional* scroll-linked translateY on
// top of its normal document-flow scrolling — never on the [data-land]
// section root, so this can never fight a CSS transform already on the
// wrapper (see app.css's Night Souk header comment). `onScroll({ sync: true
// })` scrubs the tween's progress directly to the element's own scroll
// position (default enter/leave thresholds already span its whole journey
// through the viewport, top edge appearing to bottom edge disappearing —
// exactly what a depth layer needs), so no separate ScrollTrigger-style
// wiring is required.
//
// Speed < 1 (background: hero stars/skyline) should lag behind normal
// scroll, speed > 1 (foreground: seller cards) should race slightly ahead.
// `(1 - speed) * travel` gives a positive (lagging/"stays put") offset for
// speed < 1 and a negative (rushing) one for speed > 1, scaled generously
// for the full-bleed hero layers and subtly for the smaller in-flow/decor
// elements elsewhere.
const HERO_TRAVEL_VH = 0.6; // ≈60vh of lag across the hero's scroll-out
const AMBIENT_TRAVEL_PX = 260; // subtle drift for categories/seller layers

function parallax() {
    if (REDUCED) return; // scroll-linked motion is exactly what the setting opts out of
    document.querySelectorAll('[data-plx]').forEach((el) => {
        const speed = parseFloat(el.dataset.plx);
        if (!Number.isFinite(speed)) return;

        const inHero = !!el.closest('[data-land="hero"]');
        const travel = inHero
            ? (1 - speed) * window.innerHeight * HERO_TRAVEL_VH
            : (1 - speed) * AMBIENT_TRAVEL_PX;

        animate(el, {
            translateY: [0, travel],
            ease: 'linear',
            autoplay: onScroll({ sync: true }),
        });
    });
}

/**
 * Scroll-triggered reveal for one section's `data-motion="item"` children.
 * Uses a plain (non-autoplay-linked) scroll observer whose `onEnter` fires
 * the reveal once — with `repeat: false` the observer stops re-processing
 * after it fires, so scrolling back up and down never replays it, and
 * (unlike linking the animation to the observer's own play/pause sync) the
 * entrance always plays to completion instead of risking a pause mid-tween
 * if the user scrolls past quickly.
 */
function sectionReveal(landKey, { staggerMs = 100 } = {}) {
    const section = document.querySelector(`[data-land="${landKey}"]`);
    if (!section) return;
    const items = section.querySelectorAll('[data-motion="item"]');
    if (!items.length) return;

    onScroll({
        target: section,
        enter: '78% top',
        repeat: false,
        onEnter: () => {
            animate(items, {
                opacity: [0, 1],
                ...(REDUCED ? {} : { translateY: [48, 0], scale: [0.97, 1] }),
                duration: REDUCED ? DUR_STD : Math.round(DUR_REVEAL * 1.35),
                ease: ENTER_EASE,
                delay: stagger(REDUCED ? Math.min(staggerMs, 60) : staggerMs),
            });
        },
    });
}

function sectionReveals() {
    sectionReveal('trust', { staggerMs: 140 });
    sectionReveal('categories', { staggerMs: 80 });
    // "Sequential" — a longer stagger reads as one-step-after-another rather
    // than a grid popping in together.
    sectionReveal('how', { staggerMs: 240 });
    sectionReveal('seller', { staggerMs: 80 });
    sectionReveal('footer-cta', { staggerMs: 80 });
}

/** Draws #how-path in sync with scroll progress through the "how" section. */
function drawHowPath() {
    if (REDUCED) return; // path stays fully drawn, as server-rendered
    const section = document.querySelector('[data-land="how"]');
    if (!section) return;
    const path = section.querySelector('#how-path');
    if (!path) return;

    const [drawable] = svg.createDrawable(path);
    animate(drawable, {
        draw: '0 1',
        ease: 'linear',
        autoplay: onScroll({ target: section, sync: true }),
    });
}

/** Stat count-ups — ends exactly on the server-rendered value/formatting. */
function countUps() {
    if (REDUCED) return; // server already rendered the exact final numbers
    const stats = document.querySelector('[data-land="stats"]');
    if (!stats) return;
    const nodes = stats.querySelectorAll('[data-countup]');
    if (!nodes.length) return;

    onScroll({
        target: stats,
        enter: '85% top',
        repeat: false,
        onEnter: () => {
            nodes.forEach((el) => {
                const target = parseInt(el.getAttribute('data-target'), 10) || 0;
                if (target <= 0) {
                    el.textContent = formatCount(target);
                    return;
                }
                const proxy = { val: 0 };
                animate(proxy, {
                    val: target,
                    duration: 2000,
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

function init() {
    // Always tear down first: wire:navigate can land back on /welcome after
    // visiting other pages, and this module (loaded once, live for the whole
    // SPA-style session) must not accumulate duplicate scroll observers.
    cleanup();

    if (!document.querySelector('[data-land]')) return; // not the landing page
    REDUCED = prefersReducedMotion(); // reduce, not remove — see header comment

    loadHouseTokens();

    scope = createScope().add(() => {
        const hero = document.querySelector('[data-land="hero"]');
        if (hero) heroEntrance(hero);
        parallax();
        sectionReveals();
        drawHowPath();
        countUps();
    });
}

// ── Spotlight cards ──
// Feeds --spot-x/--spot-y to .spot-card elements (see app.css) from ONE
// delegated listener installed at module load: no per-init teardown needed
// (scope.revert() only tracks anime objects), negligible cost on non-landing
// pages (the closest() check fails immediately), and it works regardless of
// reduced motion — pointer-following glow is input feedback, not animation.
document.addEventListener('pointermove', (e) => {
    const card = e.target.closest?.('.spot-card');
    if (!card) return;
    const r = card.getBoundingClientRect();
    card.style.setProperty('--spot-x', `${e.clientX - r.left}px`);
    card.style.setProperty('--spot-y', `${e.clientY - r.top}px`);
});

document.addEventListener('livewire:navigated', init);
init();
