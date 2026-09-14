import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../../Components/Icon';

const navItems = [
    { href: '#benefits', label: 'Benefits' },
    { href: '#features', label: 'Features' },
    { href: '#industries', label: 'Industries' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#faq', label: 'FAQ' },
];

export default function LandingNav() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const [open, setOpen] = useState(false);

    return (
        <header className="landing-nav-shell">
            <div className="landing-container landing-nav">
                <a className="landing-brand" href="#top" aria-label="Santovate CRM Home">
                    <span className="landing-brand-mark">S</span>
                    <span>
                        <strong>Santovate</strong>
                        <small>CRM</small>
                    </span>
                </a>

                <nav className="landing-nav-links" aria-label="Navigasi landing page">
                    {navItems.map((item) => (
                        <a key={item.href} href={item.href}>{item.label}</a>
                    ))}
                </nav>

                <div className="landing-nav-actions">
                    <Link className="landing-login-link" href={user ? '/dashboard' : '/login'}>
                        {user ? 'Buka Dashboard' : 'Login'}
                    </Link>
                    <a className="landing-btn landing-btn-primary landing-btn-nav" href="#pricing">
                        Request Demo
                        <Icon name="chevron" size={16}/>
                    </a>
                </div>

                <button
                    type="button"
                    className={`landing-menu-toggle ${open ? 'is-open' : ''}`}
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                    aria-label="Buka menu"
                >
                    <Icon name={open ? 'close' : 'menu'} size={22}/>
                </button>
            </div>

            {open && (
                <div className="landing-mobile-menu">
                    <div className="landing-container">
                        {navItems.map((item) => (
                            <a key={item.href} href={item.href} onClick={() => setOpen(false)}>{item.label}</a>
                        ))}
                        <Link href={user ? '/dashboard' : '/login'} onClick={() => setOpen(false)}>
                            {user ? 'Buka Dashboard' : 'Login ke CRM'}
                        </Link>
                        <a className="landing-btn landing-btn-primary" href="#pricing" onClick={() => setOpen(false)}>
                            Request Demo
                        </a>
                    </div>
                </div>
            )}
        </header>
    );
}
