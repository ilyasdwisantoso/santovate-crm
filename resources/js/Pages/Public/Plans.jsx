import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PublicShell from './PublicShell';
import Icon from '../../Components/Icon';

const rupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;
const featureLabel = (feature) => String(feature || '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function Plans({ plans = [], configurations = [] }) {
    const page = usePage();
    const query = new URLSearchParams(page.url.split('?')[1] || '');
    const defaultConfig = query.get('config')
        || configurations.find((item) => item.key === 'software-agency')?.key
        || configurations[0]?.key
        || '';
    const defaultPlan = query.get('plan') || 'growth';

    const [configurationKey, setConfigurationKey] = useState(defaultConfig);
    const [billing, setBilling] = useState('monthly');

    const configuration = useMemo(
        () => configurations.find((item) => item.key === configurationKey) || configurations[0],
        [configurations, configurationKey],
    );

    const configPrice = billing === 'annual'
        ? Number(configuration?.annual_addon_price || 0)
        : Number(configuration?.monthly_addon_price || 0);

    const planPrice = (plan) => billing === 'annual'
        ? Number(plan.annual_price || 0)
        : Number(plan.monthly_price || 0);

    const periodLabel = billing === 'annual' ? '/ tahun' : '/ bulan';
    const registerHref = (plan) => `/register?plan=${plan.key}&config=${configuration?.key || ''}`;

    return <PublicShell title="Harga Santovate CRM">
        <section className="sv-public-hero sv-price-hero-v1">
            <span>/ PRICING</span>
            <h1>Harga jelas.<br/><em>Konfigurasi sesuai bisnis.</em></h1>
            <p>Subscription Santovate CRM memiliki harga tetap. Pilih paket, lalu tambahkan Business Configuration sesuai workflow industri Anda.</p>
            <div className="sv-billing-toggle" role="group" aria-label="Billing cycle">
                <button type="button" className={billing === 'monthly' ? 'active' : ''} onClick={() => setBilling('monthly')}>Bulanan</button>
                <button type="button" className={billing === 'annual' ? 'active' : ''} onClick={() => setBilling('annual')}>Tahunan</button>
            </div>
        </section>

        <section className="sv-pricing-config-selector sv-price-config-v1">
            <div className="sv-pricing-selector-head">
                <div><small>STEP 1</small><h2>Pilih Business Configuration</h2><p>Konfigurasi mengatur pipeline, terminology, dan follow-up rule sesuai jenis bisnis.</p></div>
                <Link href="/business-configurations" className="sv-pricing-inline-link">Pelajari konfigurasi <Icon name="chevron" size={15}/></Link>
            </div>

            <div className="sv-config-choice-grid sv-config-price-grid">
                {configurations.map((item) => {
                    const active = item.key === configuration?.key;
                    const amount = billing === 'annual' ? item.annual_addon_price : item.monthly_addon_price;
                    return <button
                        key={item.key}
                        type="button"
                        className={`sv-config-choice sv-config-choice-price ${active ? 'active' : ''}`}
                        onClick={() => setConfigurationKey(item.key)}
                    >
                        <span className="sv-config-choice-copy">
                            <strong>{item.name}</strong>
                            <small>{item.industry}</small>
                        </span>
                        <span className="sv-config-choice-amount">+ {rupiah(amount)} <small>{periodLabel}</small></span>
                    </button>;
                })}
            </div>

            {configuration && <div className="sv-selected-config-banner sv-selected-config-price">
                <div><small>Business Configuration</small><strong>{configuration.name}</strong></div>
                <p>{configuration.description}</p>
                <b>+ {rupiah(configPrice)} {periodLabel}</b>
            </div>}
        </section>

        <section className="sv-plan-grid sv-plan-grid-v402 sv-plan-grid-price-v1">
            {plans.map((plan) => {
                const base = planPrice(plan);
                const total = base + configPrice;
                const recommended = plan.key === 'growth';
                const selected = plan.key === defaultPlan;
                const annualSaving = Math.max(0, Number(plan.monthly_price || 0) * 12 - Number(plan.annual_price || 0));

                return <article key={plan.id} className={`${recommended ? 'recommended' : ''} ${selected ? 'query-selected' : ''}`}>
                    {recommended && <span className="sv-recommended-chip">RECOMMENDED</span>}
                    <div className="sv-plan-title-row"><div><small>{plan.name}</small><h2>{plan.name}</h2></div><span>{plan.user_limit} user</span></div>
                    <p>{plan.description}</p>

                    <div className="sv-plan-price-main">
                        <strong>{rupiah(base)}</strong><span>{periodLabel}</span>
                        {billing === 'annual' && <small>≈ {rupiah(Math.round(base / 12))}/bulan</small>}
                    </div>

                    <div className="sv-plan-price-breakdown">
                        <div><span>Subscription</span><strong>{rupiah(base)}</strong></div>
                        <div><span>{configuration?.name || 'Business Configuration'}</span><strong>+ {rupiah(configPrice)}</strong></div>
                        <div className="total"><span>Total</span><strong>{rupiah(total)} {periodLabel}</strong></div>
                    </div>

                    {billing === 'annual' && annualSaving > 0 && <div className="sv-saving-note">Hemat {rupiah(annualSaving)} dibanding pembayaran bulanan 12x.</div>}

                    <div className="sv-plan-meta">
                        <span>{plan.user_limit} user</span>
                        <span>{Number(plan.prospect_limit).toLocaleString('id-ID')} prospect</span>
                    </div>

                    <ul>{(plan.features || []).map((feature) => <li key={feature}><Icon name="check" size={14}/> {featureLabel(feature)}</li>)}</ul>

                    <Link href={registerHref(plan)} className={`btn ${recommended ? 'btn-primary' : 'btn-secondary'}`}>Pilih {plan.name}</Link>
                </article>;
            })}
        </section>

        <section className="sv-custom-scope-note">
            <div className="sv-custom-scope-icon"><Icon name="spark" size={21}/></div>
            <div>
                <small>CUSTOM EXTENSION</small>
                <h2>Custom development dihitung terpisah dari subscription.</h2>
                <p>Contoh: Create Order, Shipment Tracking, Customer Portal, API/aggregator integration, custom approval workflow, custom dashboard, dan feature extension lain berdasarkan hasil discovery.</p>
            </div>
            <Link href="/contact" className="btn btn-secondary">Diskusikan kebutuhan</Link>
        </section>
    </PublicShell>;
}
