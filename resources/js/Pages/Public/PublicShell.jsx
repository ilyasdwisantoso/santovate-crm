import { Head, Link } from '@inertiajs/react';

export default function PublicShell({ title, children }) {
    return <div className="sv-public">
        <Head title={title}/>
        <header className="sv-public-nav">
            <Link href="/" className="sv-public-brand"><span>S</span><strong>Santovate CRM</strong></Link>
            <nav>
                <Link href="/pricing">Paket</Link>
                <Link href="/business-configurations">Demo Bisnis</Link>
                <Link href="/faq">FAQ</Link>
                <Link href="/contact">Kontak</Link>
                <Link href="/login" className="sv-public-login">Log in</Link>
            </nav>
        </header>
        <main>{children}</main>
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
