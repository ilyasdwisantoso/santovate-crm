import Icon from '../../../Components/Icon';

export default function LandingFooter() {
    return (
        <footer className="fl-footer">
            <div className="fl-container">
                <div className="fl-footer-top">
                    <div className="fl-footer-brand">
                        <a href="#top" className="fl-brand">
                            <span className="fl-brand-symbol">S</span>
                            <strong>Santovate</strong>
                        </a>
                        <p>A modern B2B sales CRM built to make pipeline visibility, follow-up discipline, and Account Executive performance easier to manage.</p>
                    </div>

                    <div className="fl-footer-columns">
                        <div>
                            <strong>Product</strong>
                            <a href="#features">Features</a>
                            <a href="#workflow">Solutions</a>
                            <a href="#pricing">Pricing</a>
                            <a href="/login">Login</a>
                        </div>
                        <div>
                            <strong>Solutions</strong>
                            <a href="#industries">Logistics</a>
                            <a href="#industries">Software House</a>
                            <a href="#industries">Agency</a>
                            <a href="#industries">Distributor</a>
                        </div>
                        <div>
                            <strong>Company</strong>
                            <a href="#top">Santovate</a>
                            <a href="#faq">FAQ</a>
                            <a href="#pricing">Request Demo</a>
                        </div>
                    </div>
                </div>

                <div className="fl-footer-newsletter">
                    <span><strong>Built for modern B2B sales teams.</strong><small>Lead → Follow-up → Pipeline → Revenue</small></span>
                    <a href="#top">Back to top <Icon name="arrowLeft" size={15}/></a>
                </div>

                <div className="fl-footer-bottom">
                    <span>© {new Date().getFullYear()} Santovate Digital Solution.</span>
                    <span>Santovate CRM · Jakarta, Indonesia</span>
                </div>
            </div>
        </footer>
    );
}
