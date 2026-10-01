import { Link, router, usePage } from '@inertiajs/react';
import Icon from '../../../Components/Icon';

export default function LandingFooter() {
    const { auth, subscription } = usePage().props;
    const user = auth?.user;
    const hasActiveSubscription = subscription?.status === 'active';
    const accountHref = !user ? '/login' : hasActiveSubscription ? '/dashboard' : '/subscription/checkout';
    const accountLabel = !user ? 'Login' : hasActiveSubscription ? 'Dashboard' : 'Lanjut Checkout';

    return <footer className="fl-footer">
        <div className="fl-container">
            <div className="fl-footer-top">
                <div className="fl-footer-brand">
                    <a href="#top" className="fl-brand">
                        <img className="sv-brand-logo landing" src="/images/brand/santovate-crm-logo.png" alt="Santovate CRM"/>
                        <strong>Santovate</strong>
                    </a>
                    <p>A configurable sales CRM with pipeline, follow-up, product catalog, WhatsApp workflow and subscription-ready business configurations.</p>
                </div>
                <div className="fl-footer-columns">
                    <div><strong>Product</strong><a href="#features">Features</a><a href="#workflow">Solutions</a><Link href="/pricing">Pricing</Link><Link href="/business-configurations">Business Demo</Link></div>
                    <div><strong>Support</strong><Link href="/faq">FAQ</Link><Link href="/contact">Contact</Link><a href="https://wa.me/6281293047587">WhatsApp</a><Link href={accountHref}>{accountLabel}</Link>{user && <button type="button" className="fl-footer-account-switch" onClick={() => router.post('/logout')}>Ganti akun</button>}</div>
                    <div><strong>Legal</strong><Link href="/terms">Syarat & Ketentuan</Link><Link href="/refund-policy">Refund Policy</Link><Link href="/privacy-policy">Privacy Policy</Link></div>
                </div>
            </div>
            <div className="fl-footer-newsletter"><span><strong>Built for configurable sales workflows.</strong><small>Lead → Follow-up → Pipeline → Revenue</small></span><a href="#top">Back to top <Icon name="arrowLeft" size={15}/></a></div>
            <div className="fl-footer-bottom"><span>© {new Date().getFullYear()} Santovate Digital Solution.</span><span>Subscription CRM · Indonesia</span></div>
        </div>
    </footer>;
}
