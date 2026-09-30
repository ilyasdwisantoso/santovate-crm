import { router } from '@inertiajs/react';

const REDUCED_MOTION = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const selectors = [
    ['.fl-section-heading', 'reveal-up'],
    ['.fl-benefit-feature', 'reveal-scale'],
    ['.fl-benefit-item', 'reveal-up'],
    ['.fl-feature-row .fl-feature-copy', 'reveal-left'],
    ['.fl-feature-row .fl-mockup-canvas', 'reveal-right'],
    ['.fl-feature-row.reverse .fl-feature-copy', 'reveal-right'],
    ['.fl-feature-row.reverse .fl-mockup-canvas', 'reveal-left'],
    ['.fl-why-copy > *', 'reveal-left'],
    ['.fl-why-board', 'reveal-scale'],
    ['.fl-industry-grid > article', 'reveal-up'],
    ['.fl-testimonial-grid > article', 'reveal-up'],
    ['.fl-integration-copy > *', 'reveal-left'],
    ['.fl-integration-orbit', 'reveal-scale'],
    ['.fl-pricing-grid > article', 'reveal-up'],
    ['.fl-faq-intro > *', 'reveal-left'],
    ['.fl-faq-list', 'reveal-right'],
    ['.fl-final-inner > *', 'reveal-up'],
    ['.fl-footer-top', 'reveal-up'],
];

let revealObserver = null;
let motionController = null;
let initFrame = 0;

function cleanupLandingMotion() {
    revealObserver?.disconnect();
    revealObserver = null;
    motionController?.abort();
    motionController = null;
    document.body.classList.remove('flow-motion-enabled');
}

function prepareRevealNodes(scope = document) {
    let order = 0;

    selectors.forEach(([selector, variant]) => {
        scope.querySelectorAll(selector).forEach((node) => {
            node.classList.add('flow-reveal', variant);

            const group = node.closest(
                '.fl-benefit-list, .fl-industry-grid, .fl-testimonial-grid, .fl-pricing-grid',
            );

            if (group) {
                const siblings = [...group.children];
                const index = Math.max(siblings.indexOf(node), 0);
                node.style.setProperty('--flow-delay', `${index * 60}ms`);
            } else {
                node.style.setProperty('--flow-delay', `${Math.min(order * 12, 90)}ms`);
            }

            order += 1;
        });
    });
}

function startRevealObserver() {
    const nodes = [...document.querySelectorAll('.flow-reveal')];

    if (REDUCED_MOTION()) {
        nodes.forEach((node) => node.classList.add('is-visible'));
        return;
    }

    revealObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                entry.target.classList.add('is-visible');
                revealObserver?.unobserve(entry.target);
            }
        },
        {
            threshold: 0.08,
            rootMargin: '0px 0px -4% 0px',
        },
    );

    nodes.forEach((node) => revealObserver.observe(node));
}

function setupHeroLoad() {
    const hero = document.querySelector('.fl-hero');
    if (!hero) return;

    const pieces = [
        hero.querySelector('.fl-section-label'),
        hero.querySelector('h1'),
        hero.querySelector('.fl-hero-copy > p'),
        hero.querySelector('.fl-hero-copy .fl-button-group'),
        hero.querySelector('.fl-hero-review'),
    ].filter(Boolean);

    pieces.forEach((node, index) => {
        node.classList.add('flow-load-piece');
        node.style.setProperty('--flow-load-delay', `${70 + index * 65}ms`);
    });

    hero.querySelector('.fl-hero-product')?.classList.add('flow-load-product');
}

function setupNavbar(signal) {
    const nav = document.querySelector('.fl-nav-shell');
    if (!nav) return;

    let frame = 0;
    const update = () => {
        frame = 0;
        nav.classList.toggle('is-scrolled', window.scrollY > 34);
    };
    const onScroll = () => {
        if (frame) return;
        frame = window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', onScroll, { passive: true, signal });
}

function setupHeroParallax(signal) {
    const product = document.querySelector('.fl-hero-product');
    if (!product || REDUCED_MOTION()) return;

    const card = product.querySelector('.fl-product-window') || product.firstElementChild;
    if (!card) return;

    let rect = null;
    let frame = 0;
    let nextX = 0;
    let nextY = 0;

    const measure = () => {
        rect = product.getBoundingClientRect();
    };

    const paint = () => {
        frame = 0;
        card.style.setProperty('--flow-mx', nextX.toFixed(3));
        card.style.setProperty('--flow-my', nextY.toFixed(3));
    };

    const schedulePaint = () => {
        if (frame) return;
        frame = window.requestAnimationFrame(paint);
    };

    const onMove = (event) => {
        if (window.innerWidth < 900) return;
        if (!rect) measure();
        if (!rect?.width || !rect?.height) return;

        nextX = (event.clientX - rect.left) / rect.width - 0.5;
        nextY = (event.clientY - rect.top) / rect.height - 0.5;
        schedulePaint();
    };

    const reset = () => {
        rect = null;
        nextX = 0;
        nextY = 0;
        schedulePaint();
    };

    product.addEventListener('pointerenter', measure, { passive: true, signal });
    product.addEventListener('pointermove', onMove, { passive: true, signal });
    product.addEventListener('pointerleave', reset, { passive: true, signal });
    window.addEventListener('resize', () => { rect = null; }, { passive: true, signal });
}

function setupScrollProgress(signal) {
    let frame = 0;

    const update = () => {
        frame = 0;
        const max = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1);
        const progress = Math.min(Math.max(window.scrollY / max, 0), 1);
        document.documentElement.style.setProperty('--flow-scroll-progress', progress.toFixed(4));
    };

    const onScroll = () => {
        if (frame) return;
        frame = window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', onScroll, { passive: true, signal });
}

function setupDynamicChartMotion() {
    document.querySelectorAll('.fl-bars > i').forEach((bar, index) => {
        bar.style.setProperty('--bar-delay', `${index * 55}ms`);
    });
}

function initializeLandingMotion() {
    cleanupLandingMotion();

    const page = document.querySelector('.fl-page');
    if (!page) return;

    motionController = new AbortController();
    const { signal } = motionController;

    document.body.classList.add('flow-motion-enabled');
    prepareRevealNodes(document);
    startRevealObserver();
    setupHeroLoad();
    setupNavbar(signal);
    setupHeroParallax(signal);
    setupScrollProgress(signal);
    setupDynamicChartMotion();
}

function scheduleInitialize() {
    if (initFrame) window.cancelAnimationFrame(initFrame);
    initFrame = window.requestAnimationFrame(() => {
        initFrame = window.requestAnimationFrame(() => {
            initFrame = 0;
            initializeLandingMotion();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleInitialize, { once: true });
} else {
    scheduleInitialize();
}

router.on('navigate', scheduleInitialize);
window.addEventListener('pageshow', scheduleInitialize, { passive: true });
