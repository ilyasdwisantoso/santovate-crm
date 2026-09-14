import { useEffect, useRef } from 'react';
import Icon from '../../../Components/Icon';
import HeroDashboardMockup from './HeroDashboardMockup';
import '../../../../css/landing-hero-v29.css';

const trustPeople = [
    { initials: 'AE', tone: 'blue' },
    { initials: 'AM', tone: 'violet' },
    { initials: 'SA', tone: 'cyan' },
    { initials: 'BD', tone: 'amber' },
];

const heroBenefits = [
    { icon: 'whatsapp', title: 'Faster', subtitle: 'Follow-up' },
    { icon: 'pipeline', title: 'Clear Pipeline', subtitle: 'Visibility' },
    { icon: 'target', title: 'Predictable', subtitle: 'Revenue' },
];

export default function HeroSection() {
    const heroRef = useRef(null);
    const productRef = useRef(null);

    useEffect(() => {
        const heroElement = heroRef.current;
        if (!heroElement) return undefined;

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        const frame = window.requestAnimationFrame(() => {
            heroElement.classList.add('svhero-ready');
            if (reduceMotion) heroElement.classList.add('svhero-reduced-motion');
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    useEffect(() => {
        const heroElement = heroRef.current;
        const productElement = productRef.current;
        if (!heroElement || !productElement) return undefined;

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) return undefined;

        let rafId = null;

        const updateTilt = (event) => {
            if (window.innerWidth < 1024) return;

            const rect = heroElement.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) - 0.5;
            const y = ((event.clientY - rect.top) / rect.height) - 0.5;

            if (rafId) window.cancelAnimationFrame(rafId);

            rafId = window.requestAnimationFrame(() => {
                productElement.style.setProperty('--svhero-rx', `${(-y * 1.65).toFixed(2)}deg`);
                productElement.style.setProperty('--svhero-ry', `${(x * 2.35).toFixed(2)}deg`);
                productElement.style.setProperty('--svhero-tx', `${(x * 5).toFixed(1)}px`);
                productElement.style.setProperty('--svhero-ty', `${(y * 4).toFixed(1)}px`);
            });
        };

        const resetTilt = () => {
            if (rafId) window.cancelAnimationFrame(rafId);
            productElement.style.setProperty('--svhero-rx', '0deg');
            productElement.style.setProperty('--svhero-ry', '0deg');
            productElement.style.setProperty('--svhero-tx', '0px');
            productElement.style.setProperty('--svhero-ty', '0px');
        };

        heroElement.addEventListener('pointermove', updateTilt);
        heroElement.addEventListener('pointerleave', resetTilt);

        return () => {
            if (rafId) window.cancelAnimationFrame(rafId);
            heroElement.removeEventListener('pointermove', updateTilt);
            heroElement.removeEventListener('pointerleave', resetTilt);
        };
    }, []);

    return (
        <section ref={heroRef} className="svhero svhero-v29" aria-labelledby="svhero-title">
            <div className="svhero-bg" aria-hidden="true">
                <div className="svhero-rock rock-a"/>
                <div className="svhero-rock rock-b"/>
                <div className="svhero-rock rock-c"/>
                <div className="svhero-blue-rim rim-a"/>
                <div className="svhero-blue-rim rim-b"/>
                <div className="svhero-noise"/>
                <div className="svhero-vignette"/>
            </div>

            <div className="svhero-shell">
                <div className="svhero-copy">
                    <div className="svhero-brand svhero-enter enter-1">
                        <span className="svhero-brand-mark">S</span>
                        <strong>Santovate</strong>
                        <span>CRM</span>
                    </div>

                    <div className="svhero-eyebrow svhero-enter enter-2">
                        <i/>
                        <span>SMART SALES · STRONGER RELATIONSHIPS · REAL GROWTH</span>
                    </div>

                    <h1 id="svhero-title" aria-label="B2B Sales CRM That Drives Real Revenue">
                        <span className="svhero-line line-1"><span>B2B Sales CRM</span></span>
                        <span className="svhero-line line-2"><span>That Drives</span></span>
                        <span className="svhero-line line-3 accent"><span>Real Revenue</span></span>
                    </h1>

                    <p className="svhero-description svhero-enter enter-4">
                        Manage prospects, automate follow-up, track your pipeline, and help your team close more deals with one modern CRM built for growing businesses.
                    </p>

                    <div className="svhero-actions svhero-enter enter-5">
                        <a href="#pricing" className="svhero-btn primary">
                            <span>Request Demo</span>
                            <span className="svhero-btn-arrow" aria-hidden="true">→</span>
                        </a>

                        <a href="#features" className="svhero-btn secondary">
                            <span className="svhero-play" aria-hidden="true">▶</span>
                            <span>Explore Features</span>
                        </a>
                    </div>

                    <div className="svhero-proof svhero-enter enter-6">
                        <div className="svhero-avatar-stack" aria-hidden="true">
                            {trustPeople.map((person) => (
                                <span
                                    key={person.initials}
                                    className={`svhero-proof-avatar tone-${person.tone}`}
                                >
                                    {person.initials}
                                </span>
                            ))}
                            <span className="svhero-proof-avatar more">+2</span>
                        </div>

                        <div className="svhero-stars" aria-label="Rated 4.8 out of 5">
                            ★★★★★
                        </div>

                        <strong>4.8/5</strong>
                        <small>from 600+ business users</small>
                    </div>

                    <div className="svhero-benefits svhero-enter enter-7">
                        {heroBenefits.map((item) => (
                            <div className="svhero-benefit" key={item.title}>
                                <span className="svhero-benefit-icon">
                                    <Icon name={item.icon} size={19}/>
                                </span>
                                <span>
                                    <strong>{item.title}</strong>
                                    <small>{item.subtitle}</small>
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                <div ref={productRef} className="svhero-product svhero-enter enter-product">
                    <div className="svhero-product-glow" aria-hidden="true"/>
                    <HeroDashboardMockup/>

                    <article className="svhero-float-card new-lead">
                        <span className="svhero-float-icon violet">
                            <Icon name="users" size={17}/>
                        </span>
                        <span>
                            <small>New Lead Assigned</small>
                            <strong>TechCorp Indonesia</strong>
                            <em>Set up discovery call</em>
                        </span>
                        <time>2m</time>
                    </article>

                    <article className="svhero-float-card deal-won">
                        <span className="svhero-float-icon mint">
                            <Icon name="check" size={18}/>
                        </span>
                        <span>
                            <small>Deal Moved to Won</small>
                            <strong>BluePeak Solutions</strong>
                            <em>Rp28M · Annual Contract</em>
                        </span>
                        <time>12m</time>
                    </article>
                </div>
            </div>

            <div className="svhero-bottom-fade" aria-hidden="true"/>
        </section>
    );
}
