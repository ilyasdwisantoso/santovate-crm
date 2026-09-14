import { Link, usePage } from '@inertiajs/react';
import PublicShell from './PublicShell';

const rupiah = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

export default function BusinessConfigurations({ configurations, plans }) {
    const selectedPlan = new URLSearchParams(usePage().url.split('?')[1] || '').get('plan') || 'starter';
    const starter = plans?.find((plan) => plan.key === 'starter') || plans?.[0];

    return <PublicShell title="Demo Konfigurasi Bisnis">
        <section className="sv-public-hero">
            <span>/ BUSINESS CONFIGURATION</span>
            <h1>Satu CRM core.<br/><em>Empat karakter bisnis.</em></h1>
            <p>Setiap konfigurasi mempunyai warna, pipeline, terminology, follow-up rule dan harga berbeda.</p>
        </section>

        <section className="sv-config-grid sv-config-grid-v402">
            {configurations.map((config) => {
                const theme = config.theme || {};
                const starterTotal = Number(starter?.monthly_price || 0) + Number(config.monthly_addon_price || 0);
                return <article
                    key={config.id}
                    style={{
                        '--config-primary': theme.primary || '#4F46E5',
                        '--config-secondary': theme.secondary || '#7C3AED',
                        '--config-accent': theme.accent || '#8B5CF6',
                        '--config-soft': theme.soft || '#EEF2FF',
                        '--config-surface': theme.surface || '#F8F7FF',
                    }}
                >
                    <div className="sv-config-colorbar"/>
                    <div className="sv-config-head">
                        <span>{config.industry}</span>
                        <strong>Mulai {rupiah(starterTotal)}/bln</strong>
                    </div>
                    <h2>{config.name}</h2>
                    <p>{config.description}</p>
                    <div className="sv-stage-row">
                        {(config.pipeline || []).map((stage) => <i key={stage.key}>{stage.label}</i>)}
                    </div>
                    <div className="sv-demo-block">
                        <strong>Follow-up</strong>
                        <span>H-{config.follow_up_rules?.lead_age_days ?? 3} initial action · {config.follow_up_rules?.no_reply_days ?? 5} hari no reply</span>
                    </div>
                    {config.demo_products?.length > 0 && <div className="sv-demo-products">
                        {config.demo_products.map((product) => <span key={product.sku}>
                            <b>{product.name}</b>
                            <small>{product.variant} · {rupiah(product.price)}</small>
                        </span>)}
                    </div>}
                    <Link className="btn btn-primary" href={`/plans?config=${config.key}&plan=${selectedPlan}`}>
                        Lihat Harga {config.name}
                    </Link>
                </article>;
            })}
        </section>
    </PublicShell>;
}
