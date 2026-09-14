import '../css/app.css';
import '../css/v24-mobile.css';
import '../css/landing.css';
import '../css/v25-mobile-menu-fix.css';
import '../css/landing-v30-helvetica.css';
import '../css/v31-mobile-power.css';
import '../css/v32-hero-mobile-elegant.css';
import './landing-motion';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

createInertiaApp({
    title: (title) => title ? `${title} Â· Santovate CRM` : 'Santovate CRM',
    resolve: (name) => pages[`./Pages/${name}.jsx`],
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#4f6eff', showSpinner: false },
});



