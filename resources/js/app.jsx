import '@fontsource-variable/plus-jakarta-sans';
import '../css/app.css';
import '../css/v24-mobile.css';
import '../css/landing.css';
import '../css/v25-mobile-menu-fix.css';
import '../css/landing-v30-helvetica.css';
import '../css/v31-mobile-power.css';
import '../css/v32-hero-mobile-elegant.css';
import '../css/v33-mobile-nav-workflow.css';
import '../css/v34-full-nav-color-bottom.css';
import '../css/v40-saas.css';
import '../css/v4022-v34-landing-restore.css';
import '../css/typography-motion-v4.css';
import '../css/finance-batch3.css';
import '../css/premium-design-system.css';
import '../css/public-pricing-v1.css';
import '../css/subscription-checkout-v2.css';
import '../css/subscription-checkout-v2-1.css';
import '../css/brand-v1.css';
import '../css/platform-batch5a.css';
import '../css/entitlements-batch5b.css';
import '../css/subscription-addons-batch5c.css';
import '../css/direct-payment-hotfix-5c2.css';
import '../css/payment-experience-hotfix-5c3.css';
import '../css/payment-result-v4.css';
import '../css/payment-flow-v5.css';
import '../css/payment-scroll-v6.css';
import './landing-motion';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

createInertiaApp({
    title: (title) => title ? title + ' \u00B7 Santovate CRM' : 'Santovate CRM',
    resolve: (name) => pages[`./Pages/${name}.jsx`],
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#5365ee', showSpinner: false },
});
