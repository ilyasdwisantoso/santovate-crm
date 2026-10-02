import { useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function PublicShell({ title, children, wide = false }) {
    const { auth, subscription } = usePage().props;
    const user = auth?.user;
    const hasActiveSubscription = subscription?.status === 'active';

    useEffect(() => {
        const html = document.documentElement;
        const body = document.body;
        const scrollClass = 'sv-payment-document-scroll';

        if (!wide) {
            html.classList.remove(scrollClass);
            body.classList.remove(scrollClass);
            return undefined;
        }

        html.classList.add(scrollClass);
        body.classList.add(scrollClass);

        return () => {
            html.classList.remove(scrollClass);
            body.classList.remove(scrollClass);
        };
    }, [wide]);

    const switchAccount = () => {
        router.post('/logout');
    };

    return <div className={`sv-public ${wide ? 'sv-public--wide' : ''}`}>
        <Head title={title}/>
        <header className="sv-public-nav">
            <Link href="/" className="sv-public-brand">
                <img className="sv-brand-logo public" src="/images/brand/santovate-crm-logo.png" alt="Santovate CRM"/>
                <strong>Santovate CRM</strong>
            </Link>
            <nav>
                <Link href="/pricing">Paket</Link>
                <Link href="/business-configurations">Demo Bisnis</Link>
                <Link href="/faq">FAQ</Link>
                <Link href="/contact">Kontak</Link>

                {!user && <Link href="/login" className="sv-public-login">Log in</Link>}

                {user && hasActiveSubscription && (
                    <Link href="/dashboard" className="sv-public-login">Dashboard</Link>
                )}

                {user && (
                    <button type="button" className="sv-public-switch-account" onClick={switchAccount}>
                        Ganti akun
                    </button>
                )}
            </nav>
        </header>
        <main className={wide ? 'sv-public-main-wide' : undefined}>{children}</main>
        <footer className="sv-public-footer">
            <span>© {new Date().getFullYear()} Santovate Digital Solution</span>
            <nav>
                <Link href="/terms">Syarat & Ketentuan</Link>
                <Link href="/refund-policy">Refund Policy</Link>
                <Link href="/privacy-policy">Privacy</Link>
                <Link href="/contact">Kontak</Link>
            </nav>
        </footer>
    </div>;
}
