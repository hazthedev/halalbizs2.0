import { animate, arc, motionValue } from 'motion';

// Faithful port of Motion's "add to basket" example
// (https://examples.motion.dev/js/add-to-basket), adapted to fly the PDP
// product image along an arc into the header cart icon, then knock the icon
// with the flyer's own arrival velocity and ripple a ring out from it.

const strength = 0.5;
const peak = 0.15;
const rotate = 0.9;
const duration = 0.45;
const basketVelocityFactor = 0.05;
const ease = [0.74, 0.18, 0.93, 0.69];

const reducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// Clone an element as a fixed overlay sitting exactly over `rect`, stripped of
// framework attributes so Alpine/Livewire never try to (re)initialise it.
function overlayClone(source, rect) {
    const flyer = source.cloneNode(true);
    [...flyer.attributes].forEach((a) => {
        if (/^(x-|wire:|:|@)/.test(a.name)) flyer.removeAttribute(a.name);
    });
    if (flyer.tagName === 'IMG') flyer.src = source.currentSrc || source.src;
    Object.assign(flyer.style, {
        position: 'fixed',
        left: `${rect.left}px`,
        top: `${rect.top}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
        margin: '0',
        objectFit: 'cover',
        borderRadius: getComputedStyle(source).borderRadius,
        zIndex: '9999',
        pointerEvents: 'none',
        willChange: 'transform, opacity',
    });
    return flyer;
}

// Fly `source` into `target` (defaults to the header cart icon).
window.flyToCart = function flyToCart(source, target = document.getElementById('cart-icon')) {
    if (!source || !target || reducedMotion()) return;

    const from = source.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    if (!from.width || !to.width) return;

    const flyer = overlayClone(source, from);
    document.body.appendChild(flyer);

    // Centre-to-centre delta so the product lands in the cart, independent of
    // the scale applied along the way.
    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const flyScale = to.width / from.width;

    // Probe the same travel on motion values so we can read arrival velocity.
    const probeX = motionValue(0);
    const probeY = motionValue(0);

    Promise.all([
        animate(
            flyer,
            { x: dx, y: dy, scale: flyScale, opacity: [1, 1, 0] },
            {
                duration,
                ease,
                path: arc({ strength, peak, rotate, direction: 'cw' }),
                opacity: { inherit: true, times: [0, 0.95, 1] },
            },
        ),
        animate(probeX, dx, { duration, ease }),
        animate(probeY, dy, { duration, ease }),
    ]).then(() => {
        flyer.remove();

        // Knock the cart icon with the flyer's own arrival velocity, then let a
        // spring settle it back to rest.
        animate(
            target,
            { x: 0, y: 0 },
            {
                type: 'spring',
                stiffness: 500,
                damping: 12,
                x: { velocity: probeX.getVelocity() * basketVelocityFactor },
                y: { velocity: probeY.getVelocity() * basketVelocityFactor },
            },
        );

        // Ripple an outline out from the cart icon as it takes the hit.
        const ring = document.createElement('div');
        Object.assign(ring.style, {
            position: 'fixed',
            left: `${to.left}px`,
            top: `${to.top}px`,
            width: `${to.width}px`,
            height: `${to.height}px`,
            border: '1px solid currentColor',
            borderRadius: getComputedStyle(target).borderRadius,
            color: getComputedStyle(target).color,
            opacity: '0',
            pointerEvents: 'none',
            zIndex: '9998',
            willChange: 'transform, opacity',
        });
        document.body.appendChild(ring);
        animate(ring, { scale: [1, 2.2], opacity: [0.8, 0] }, { duration: 0.5, ease: 'easeOut' })
            .finished.then(() => ring.remove());
    });
};
