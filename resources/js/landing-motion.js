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

function prepareRevealNodes(scope = document) {
    let order = 0;

    selectors.forEach(([selector, variant]) => {
        scope.querySelectorAll(selector).forEach((node) => {
            if (node.dataset.flowMotionReady === '1') return;
            node.dataset.flowMotionReady = '1';
            node.classList.add('flow-reveal', variant);

            const group =
                node.closest('.fl-benefit-list, .fl-industry-grid, .fl-testimonial-grid, .fl-pricing-grid');

            if (group) {
                const siblings = [...group.children];
                const index = Math.max(siblings.indexOf(node), 0);
                node.style.setProperty('--flow-delay', `${index * 85}ms`);
            } else {
                node.style.setProperty('--flow-delay', `${Math.min(order * 18, 140)}ms`);
            }
            order += 1;
        });
    });
}

let revealObserver;

function startRevealObserver() {
    if (REDUCED_MOTION()) {
        document.querySelectorAll('.flow-reveal').forEach((node) => node.classList.add('is-visible'));
        return;
    }

    revealObserver?.disconnect();
    revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            });
        },
        {
            threshold: 0.12,
            rootMargin: '0px 0px -7% 0px',
        },
    );

    document.querySelectorAll('.flow-reveal').forEach((node) => revealObserver.observe(node));
}

function setupHeroLoad() {
    const hero = document.querySelector('.fl-hero');
    if (!hero || hero.dataset.flowHeroReady === '1') return;
    hero.dataset.flowHeroReady = '1';

    const pieces = [
        hero.querySelector('.fl-section-label'),
        hero.querySelector('h1'),
        hero.querySelector('.fl-hero-copy > p'),
        hero.querySelector('.fl-hero-copy .fl-button-group'),
        hero.querySelector('.fl-hero-review'),
    ].filter(Boolean);

    pieces.forEach((node, index) => {
        node.classList.add('flow-load-piece');
        node.style.setProperty('--flow-load-delay', `${100 + index * 90}ms`);
    });

    const product = hero.querySelector('.fl-hero-product');
    product?.classList.add('flow-load-product');
}

function setupNavbar() {
    const nav = document.querySelector('.fl-nav-shell');
    if (!nav || nav.dataset.flowNavReady === '1') return;
    nav.dataset.flowNavReady = '1';

    const update = () => {
        nav.classList.toggle('is-scrolled', window.scrollY > 34);
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
}

function setupHeroParallax() {
    const product = document.querySelector('.fl-hero-product');
    if (!product || product.dataset.flowParallaxReady === '1') return;
    product.dataset.flowParallaxReady = '1';

    const card = product.querySelector('.fl-product-window') || product.firstElementChild;
    if (!card) return;

    const onMove = (event) => {
        if (window.innerWidth < 900 || REDUCED_MOTION()) return;
        const rect = product.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        card.style.setProperty('--flow-mx', x.toFixed(3));
        card.style.setProperty('--flow-my', y.toFixed(3));
    };

    const reset = () => {
        card.style.setProperty('--flow-mx', '0');
        card.style.setProperty('--flow-my', '0');
    };

    product.addEventListener('pointermove', onMove);
    product.addEventListener('pointerleave', reset);
}

function setupScrollProgress() {
    if (document.documentElement.dataset.flowScrollReady === '1') return;
    document.documentElement.dataset.flowScrollReady = '1';

    let scheduled = false;
    const update = () => {
        const max = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1);
        const progress = Math.min(Math.max(window.scrollY / max, 0), 1);
        document.documentElement.style.setProperty('--flow-scroll-progress', progress.toFixed(4));
        document.documentElement.style.setProperty('--flow-scroll-y', `${window.scrollY}px`);
        scheduled = false;
    };

    const onScroll = () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', onScroll, { passive: true });
}

function setupDynamicChartMotion() {
    document.querySelectorAll('.fl-bars > i').forEach((bar, index) => {
        bar.style.setProperty('--bar-delay', `${index * 70}ms`);
    });
}

function initializeLandingMotion() {
    const page = document.querySelector('.fl-page');
    if (!page) return;

    document.body.classList.add('flow-motion-enabled');

    prepareRevealNodes(document);
    startRevealObserver();
    setupHeroLoad();
    setupNavbar();
    setupHeroParallax();
    setupScrollProgress();
    setupDynamicChartMotion();
}

let mutationTimer;

const mutationObserver = new MutationObserver(() => {
    window.clearTimeout(mutationTimer);
    mutationTimer = window.setTimeout(initializeLandingMotion, 40);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeLandingMotion();
        mutationObserver.observe(document.body, { childList: true, subtree: true });
    });
} else {
    initializeLandingMotion();
    mutationObserver.observe(document.body, { childList: true, subtree: true });
}
