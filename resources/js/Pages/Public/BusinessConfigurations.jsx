import { Link, usePage } from '@inertiajs/react';
import PublicShell from './PublicShell';
import Icon from '../../Components/Icon';

const rupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;

export default function BusinessConfigurations({ configurations = [], plans = [] }) {
    const selectedPlan = new URLSearchParams(usePage().url.split('?')[1] || '').get('plan') || 'starter';
    const plan = plans.find((item) => item.key === selectedPlan) || plans[0];
    const baseMonthly = Number(plan?.monthly_price || 0);
    const baseAnnual = Number(plan?.annual_price || 0);

    return <PublicShell title="Business Configuration & Pricing">
        <section className="sv-public-hero sv-price-hero-v1">
            <span>/ BUSINESS CONFIGURATION</span>
            <h1>Satu CRM core.<br/><em>Workflow yang mengikuti bisnis Anda.</em></h1>
            <p>Business Configuration bukan custom development. Ini adalah konfigurasi standar Santovate untuk pipeline, terminology, follow-up rule, dan workflow industri.</p>
        </section>

        <section className="sv-config-pricing-summary">
            <div><small>Base plan untuk contoh</small><strong>{plan?.name || 'Starter'}</strong><span>{rupiah(baseMonthly)}/bulan</span></div>
            <p>Pilih konfigurasi di bawah untuk melihat biaya tambahan dan total subscription. Paket dapat diganti kembali di halaman Pricing.</p>
            <Link href={`/pricing?plan=${plan?.key || 'starter'}`} className="btn btn-secondary">Bandingkan semua paket</Link>
        </section>

        <section className="sv-config-grid sv-config-grid-v402 sv-config-detail-price-grid">
            {configurations.map((config) => {
                const theme = config.theme || {};
                const monthly = Number(config.monthly_addon_price || 0);
                const annual = Number(config.annual_addon_price || 0);
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
                    <div className="sv-config-head"><span>{config.industry}</span><strong>Standard workflow</strong></div>
                    <h2>{config.name}</h2>
                    <p>{config.description}</p>

                    <div className="sv-config-price-box">
                        <div><span>Configuration</span><strong>+ {rupiah(monthly)}/bulan</strong><small>{rupiah(annual)}/tahun</small></div>
                        <div className="total"><span>Total + {plan?.name || 'plan'}</span><strong>{rupiah(baseMonthly + monthly)}/bulan</strong><small>{rupiah(baseAnnual + annual)}/tahun</small></div>
                    </div>

                    <div className="sv-stage-row">
                        {(config.pipeline || []).map((stage) => <i key={stage.key}>{stage.label}</i>)}
                    </div>
                    <div className="sv-demo-block">
                        <strong>Follow-up rule</strong>
                        <span>H-{config.follow_up_rules?.lead_age_days ?? 3} initial action · {config.follow_up_rules?.no_reply_days ?? 5} hari no reply</span>
                    </div>

                    <Link className="btn btn-primary" href={`/pricing?config=${config.key}&plan=${plan?.key || selectedPlan}`}>
                        Hitung paket {config.name} <Icon name="chevron" size={15}/>
                    </Link>
                </article>;
            })}
        </section>

        <section className="sv-custom-scope-note sv-config-custom-note">
            <div className="sv-custom-scope-icon"><Icon name="spark" size={21}/></div>
            <div>
                <small>DI LUAR BUSINESS CONFIGURATION</small>
                <h2>Extension dan integration mengikuti scope client.</h2>
                <p>Create Order, Shipment Tracking, Customer Portal, API carrier/agregator, ERP integration, custom dashboard, atau fitur bespoke lainnya dibuat sebagai quotation project terpisah.</p>
            </div>
            <Link href="/contact" className="btn btn-secondary">Konsultasi custom scope</Link>
        </section>
    </PublicShell>;
}
