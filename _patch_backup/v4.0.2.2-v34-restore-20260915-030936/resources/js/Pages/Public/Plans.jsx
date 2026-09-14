import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PublicShell from './PublicShell';

const rupiah = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

export default function Plans({ plans, configurations }) {
    const page = usePage();
    const query = new URLSearchParams((page.url.split('?')[1] || ''));
    const defaultKey = query.get('config') || configurations?.find((item) => item.key === 'parfum-retail')?.key || configurations?.[0]?.key || '';
    const [configurationKey, setConfigurationKey] = useState(defaultKey);
    const [billing, setBilling] = useState('monthly');

    const configuration = useMemo(
        () => configurations.find((item) => item.key === configurationKey) || configurations[0],
        [configurations, configurationKey],
    );

    const theme = configuration?.theme || {};
    const themeStyle = {
        '--config-primary': theme.primary || '#4F46E5',
        '--config-secondary': theme.secondary || '#7C3AED',
        '--config-accent': theme.accent || '#8B5CF6',
        '--config-soft': theme.soft || '#EEF2FF',
        '--config-surface': theme.surface || '#F8F7FF',
    };

    return <PublicShell title="Paket Santovate CRM">
        <section className="sv-public-hero">
            <span>/ SUBSCRIPTION</span>
            <h1>Mulai 1 user<br/><em>Rp250 ribu / bulan.</em></h1>
            <p>Harga akhir berbeda menurut konfigurasi bisnis. Pilih bisnis terlebih dahulu, lalu paket CRM.</p>
        </section>

        <section className="sv-pricing-config-selector" style={themeStyle}>
            <div className="sv-pricing-selector-head">
                <div><small>STEP 1</small><h2>Pilih konfigurasi bisnis</h2></div>
                <div className="sv-billing-toggle">
                    <button className={billing === 'monthly' ? 'active' : ''} onClick={() => setBilling('monthly')}>Bulanan</button>
                    <button className={billing === 'annual' ? 'active' : ''} onClick={() => setBilling('annual')}>Tahunan</button>
                </div>
            </div>
            <div className="sv-config-choice-grid">
                {configurations.map((item) => {
                    const itemTheme = item.theme || {};
                    const active = item.key === configuration?.key;
                    return <button
                        key={item.key}
                        type="button"
                        className={`sv-config-choice ${active ? 'active' : ''}`}
                        style={{
                            '--choice-primary': itemTheme.primary || '#4F46E5',
                            '--choice-soft': itemTheme.soft || '#EEF2FF',
                        }}
                        onClick={() => setConfigurationKey(item.key)}
                    >
                        <i/>
                        <span><strong>{item.name}</strong><small>{item.industry}</small></span>
                        <b>{item.monthly_addon_price ? `+${rupiah(item.monthly_addon_price)}` : 'Tanpa add-on'}</b>
                    </button>;
                })}
            </div>
            {configuration && <div className="sv-selected-config-banner">
                <span className="sv-selected-config-dot"/>
                <div><small>Konfigurasi aktif untuk simulasi</small><strong>{configuration.name}</strong></div>
                <p>{configuration.description}</p>
            </div>}
        </section>

        <section className="sv-plan-grid sv-plan-grid-v402" style={themeStyle}>
            {plans.map((plan) => {
                const base = billing === 'annual' ? plan.annual_price : plan.monthly_price;
                const addon = billing === 'annual'
                    ? configuration?.annual_addon_price
                    : configuration?.monthly_addon_price;
                const total = Number(base || 0) + Number(addon || 0);
                const unit = billing === 'annual' ? '/tahun' : '/bulan';

                return <article key={plan.id} className={plan.key === 'growth' ? 'recommended' : ''}>
                    {plan.key === 'growth' && <span className="sv-recommended-chip">RECOMMENDED</span>}
                    <small>{plan.name}</small>
                    <h2>{rupiah(total)}<span>{unit}</span></h2>
                    <p>{plan.description}</p>
                    <div className="sv-price-breakdown-v402">
                        <span>Base plan <b>{rupiah(base)}</b></span>
                        <span>{configuration?.name} <b>+ {rupiah(addon)}</b></span>
                    </div>
                    <div className="sv-plan-meta">
                        <span>{plan.user_limit} user</span>
                        <span>{Number(plan.prospect_limit).toLocaleString('id-ID')} prospect</span>
                    </div>
                    <ul>{(plan.features || []).map((feature) => <li key={feature}>✓ {feature.replaceAll('_', ' ')}</li>)}</ul>
                    <Link
                        href={`/register?plan=${plan.key}&config=${configuration?.key}&billing=${billing}`}
                        className="btn btn-primary"
                    >
                        Pilih {plan.name} · {configuration?.name}
                    </Link>
                </article>;
            })}
        </section>
    </PublicShell>;
}
