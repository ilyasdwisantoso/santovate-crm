import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PublicShell from './PublicShell';

const featureLabel = (feature) => feature
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function Plans({ plans = [], configurations = [] }) {
    const page = usePage();
    const query = new URLSearchParams((page.url.split('?')[1] || ''));
    const defaultKey = query.get('config')
        || configurations.find((item) => item.key === 'software-agency')?.key
        || configurations[0]?.key
        || '';
    const [configurationKey, setConfigurationKey] = useState(defaultKey);

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
            <span>/ PRICING & PACKAGES</span>
            <h1>Pilih paket sesuai<br/><em>skala tim sales Anda.</em></h1>
            <p>Harga tidak ditampilkan di halaman publik. Nilai implementasi disesuaikan setelah kebutuhan, konfigurasi bisnis, integrasi, dan scope onboarding dikonfirmasi.</p>
        </section>

        <section className="sv-pricing-config-selector" style={themeStyle}>
            <div className="sv-pricing-selector-head">
                <div><small>STEP 1</small><h2>Pilih konfigurasi bisnis</h2></div>
                <span className="sv-pricing-private-note">Harga diberikan setelah konsultasi</span>
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
                        <b>Workflow khusus</b>
                    </button>;
                })}
            </div>

            {configuration && <div className="sv-selected-config-banner">
                <span className="sv-selected-config-dot"/>
                <div><small>Konfigurasi yang dipilih</small><strong>{configuration.name}</strong></div>
                <p>{configuration.description}</p>
            </div>}
        </section>

        <section className="sv-plan-grid sv-plan-grid-v402" style={themeStyle}>
            {plans.map((plan) => <article key={plan.id} className={plan.key === 'growth' ? 'recommended' : ''}>
                {plan.key === 'growth' && <span className="sv-recommended-chip">RECOMMENDED</span>}
                <small>{plan.name}</small>
                <h2 className="sv-package-title">
                    {plan.key === 'starter' && 'Essential CRM'}
                    {plan.key === 'growth' && 'Sales Automation'}
                    {plan.key === 'scale' && 'Advanced Operations'}
                </h2>
                <p>{plan.description}</p>

                <div className="sv-plan-meta">
                    <span>{plan.user_limit} user</span>
                    <span>{Number(plan.prospect_limit).toLocaleString('id-ID')} prospect</span>
                </div>

                <ul>{(plan.features || []).map((feature) => <li key={feature}>✓ {featureLabel(feature)}</li>)}</ul>

                <Link
                    href={`/register?plan=${plan.key}&config=${configuration?.key || ''}`}
                    className="btn btn-primary"
                >
                    Pilih {plan.name}
                </Link>
                <small className="sv-package-price-note">Harga diinformasikan setelah kebutuhan dikonfirmasi.</small>
            </article>)}
        </section>
    </PublicShell>;
}
