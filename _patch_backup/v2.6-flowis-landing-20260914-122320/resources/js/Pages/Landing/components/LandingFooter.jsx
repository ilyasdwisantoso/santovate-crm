export default function LandingFooter() {
    return (
        <footer className="landing-footer">
            <div className="landing-container landing-footer-grid">
                <div className="landing-footer-brand">
                    <a className="landing-brand landing-brand-footer" href="#top">
                        <span className="landing-brand-mark">S</span>
                        <span>
                            <strong>Santovate</strong>
                            <small>CRM</small>
                        </span>
                    </a>
                    <p>Sales workspace yang membantu tim mengelola lead, follow-up, pipeline, dan performance dalam satu alur kerja yang lebih rapi.</p>
                </div>

                <div className="landing-footer-col">
                    <strong>Product</strong>
                    <a href="#features">Features</a>
                    <a href="#workflow">Workflow</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#faq">FAQ</a>
                </div>

                <div className="landing-footer-col">
                    <strong>Solutions</strong>
                    <a href="#industries">Logistics</a>
                    <a href="#industries">Software House</a>
                    <a href="#industries">Digital Agency</a>
                    <a href="#industries">B2B Distribution</a>
                </div>

                <div className="landing-footer-col">
                    <strong>Santovate</strong>
                    <a href="https://santovate.com" target="_blank" rel="noreferrer">Company Website</a>
                    <a href="/login">CRM Login</a>
                    <a href="#pricing">Request Demo</a>
                </div>
            </div>
            <div className="landing-container landing-footer-bottom">
                <span>© {new Date().getFullYear()} Santovate Digital Solution.</span>
                <span>Built for modern B2B sales teams.</span>
            </div>
        </footer>
    );
}
