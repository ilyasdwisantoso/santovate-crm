import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import Icon from '../../../Components/Icon';
import HeroDashboardMockup from './HeroDashboardMockup';
import '../../../../css/landing-hero-v28.css';

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

export default function HeroSection({ user }) {
    const heroRef = useRef(null);
    const productRef = useRef(null);

    useEffect(() => {
        const hero = heroRef.current;
        if (!hero) return undefined;

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) {
            hero.classList.add('svhero-ready', 'svhero-reduced-motion');
            return undefined;
        }

        const frame = window.requestAnimationFrame(() => {
            hero.classList.add('svhero-ready');
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    useEffect(() => {
        const hero = heroRef.current;
        const product = productRef.current;
        if (!hero || !product) return undefined;

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) return undefined;

        let scheduled = false;
        let pointerX = 0;
        let pointerY = 0;

        const paint = () => {
            product.style.setProperty('--svhero-rx', `${(-pointerY * 2.15).toFixed(2)}deg`);
            product.style.setProperty('--svhero-ry', `${(pointerX * 3.1).toFixed(2)}deg`);
            product.style.setProperty('--svhero-tx', `${(pointerX * 7).toFixed(1)}px`);
            product.style.setProperty('--svhero-ty', `${(pointerY * 5).toFixed(1)}px`);
            scheduled = false;
        };

        const onPointerMove = (event) => {
            if (window.innerWidth < 900) return;

            const rect = hero.getBoundingClientRect();
            pointerX = ((event.clientX - rect.left) / rect.width) - 0.5;
            pointerY = ((event.clientY - rect.top) / rect.height) - 0.5;

            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(paint);
            }
        };

        const onPointerLeave = () => {
            pointerX = 0;
            pointerY = 0;
            product.style.setProperty('--svhero-rx', '0deg');
            product.style.setProperty('--svhero-ry', '0deg');
            product.style.setProperty('--svhero-tx', '0px');
            product.style.setProperty('--svhero-ty', '0px');
        };

        hero.addEventListener('pointermove', onPointerMove);
        hero.addEventListener('pointerleave', onPointerLeave);

        return () => {
            hero.removeEventListener('pointermove', onPointerMove);
            hero.removeEventListener('pointerleave', onPointerLeave);
        };
    }, []);

    return (
        <section ref={heroRef} className="svhero" aria-labelledby="svhero-title">
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

                    <h1 id="svhero-title" className="svhero-enter enter-3">
                        B2B Sales CRM
                        <span>That Drives</span>
                        <em>Real Revenue</em>
                    </h1>

                    <p className="svhero-description svhero-enter enter-4">
                        Manage prospects, automate follow-up, track your pipeline, and help your team close more deals with one modern CRM built for growing businesses.
                    </p>

                    <div className="svhero-actions svhero-enter enter-5">
                        <a href="#pricing" className="svhero-btn primary">
                            Request Demo
                            <span className="svhero-btn-arrow">→</span>
                        </a>

                        <a href="#features" className="svhero-btn secondary">
                            <span className="svhero-play">▶</span>
                            Explore Features
                        </a>
                    </div>

                    <div className="svhero-proof svhero-enter enter-6">
                        <div className="svhero-avatar-stack">
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
                            <span>★★★★★</span>
                        </div>

                        <strong>4.8/5</strong>
                        <small>from 600+ business users</small>
                    </div>

                    <div className="svhero-benefits svhero-enter enter-7">
                        {heroBenefits.map((item) => (
                            <div className="svhero-benefit" key={item.title}>
                                <span className="svhero-benefit-icon">
                                    <Icon name={item.icon} size={18}/>
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
                        <time>2m ago</time>
                    </article>

                    <article className="svhero-float-card deal-won">
                        <span className="svhero-float-icon mint">
                            <Icon name="check" size={18}/>
                        </span>
                        <span>
                            <small>Deal Moved to Won</small>
                            <strong>BluePeak Solutions</strong>
                            <em>Rp28.000.000 · Annual Contract</em>
                        </span>
                        <time>12m ago</time>
                    </article>

                    <div className="svhero-script" aria-hidden="true">
                        Relationships
                        <span>Build Revenue</span>
                    </div>
                </div>
            </div>

            <div className="svhero-bottom-fade" aria-hidden="true"/>
        </section>
    );
}
