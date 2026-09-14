import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../../Components/Icon';

const navItems = [
    { href: '#features', label: 'Features' },
    { href: '#industries', label: 'Industries' },
];

export default function LandingNav() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const [open, setOpen] = useState(false);

    return (
        <header className="fl-nav-shell">
            <div className="fl-container fl-nav">
                <a href="#top" className="fl-brand" aria-label="Santovate CRM">
                    <span className="fl-brand-symbol">S</span>
                    <strong>Santovate</strong>
                </a>

                <nav className="fl-nav-links" aria-label="Landing page navigation">
                    {navItems.map((item) => <a href={item.href} key={item.href}>{item.label}</a>)}
                </nav>

                <div className="fl-nav-actions">
                    <Link href={user ? '/dashboard' : '/login'} className="fl-nav-login">
                        {user ? 'Dashboard' : 'Log in'}
                    </Link>
                    <a href="/business-configurations" className="fl-nav-cta">
                        Request Demo <Icon name="chevron" size={15}/>
                    </a>
                </div>

                <button
                    type="button"
                    className="fl-nav-toggle"
                    aria-label="Buka menu"
                    aria-expanded={open}
                    onClick={() => setOpen(!open)}
                >
                    <Icon name={open ? 'close' : 'menu'} size={20}/>
                </button>
            </div>

            <div className={`fl-mobile-nav ${open ? 'open' : ''}`}>
                <div className="fl-container">
                    {navItems.map((item) => (
                        <a href={item.href} key={item.href} onClick={() => setOpen(false)}>
                            {item.label}<Icon name="chevron" size={16}/>
                        </a>
                    ))}
                    <Link href={user ? '/dashboard' : '/login'} onClick={() => setOpen(false)}>
                        {user ? 'Open Dashboard' : 'Log in'}<Icon name="chevron" size={16}/>
                    </Link>
                    <a href="/business-configurations" className="fl-nav-cta mobile" onClick={() => setOpen(false)}>Request Demo</a>
                </div>
            </div>
        </header>
    );
}





