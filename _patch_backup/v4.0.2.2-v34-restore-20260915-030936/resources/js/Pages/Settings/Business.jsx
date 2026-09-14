import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const rupiah = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

export default function Business({ organization, activeSubscription, configurations, plans }) {
    return <AppLayout title="Business Configuration" subtitle="Workflow, warna, pipeline dan harga CRM mengikuti jenis bisnis workspace.">
        <Head title="Business Configuration"/>

        <section className="panel">
            <div className="panel-head">
                <div>
                    <span className="eyebrow">Active workspace</span>
                    <h3>{organization.name}</h3>
                    <p>{organization.business_configuration?.name || 'Belum aktif'}</p>
                </div>
                <span className="badge badge-violet">{activeSubscription?.plan?.name || 'No active plan'}</span>
            </div>
            {activeSubscription && <div className="sv-admin-summary">
                <span><small>Billing</small><strong>{activeSubscription.billing_cycle}</strong></span>
                <span><small>Berlaku sampai</small><strong>{activeSubscription.ends_at ? new Date(activeSubscription.ends_at).toLocaleDateString('id-ID') : '-'}</strong></span>
                <span><small>Limit Prospect</small><strong>{activeSubscription.plan?.prospect_limit?.toLocaleString('id-ID')}</strong></span>
            </div>}
        </section>

        <section className="panel">
            <div className="panel-head">
                <div>
                    <span className="eyebrow">Configuration library</span>
                    <h3>Demo seluruh konfigurasi</h3>
                    <p>Warna dashboard dan workflow berubah mengikuti konfigurasi aktif.</p>
                </div>
                <Link href="/plans" className="btn btn-primary">Lihat Paket & Harga</Link>
            </div>

            <div className="sv-admin-config-grid sv-admin-config-grid-v402">
                {configurations.map((config) => {
                    const theme = config.theme || {};
                    const active = organization.business_configuration?.id === config.id;
                    const starter = plans?.find((plan) => plan.key === 'starter') || plans?.[0];
                    const starterTotal = Number(starter?.monthly_price || 0) + Number(config.monthly_addon_price || 0);
                    return <article
                        key={config.id}
                        className={active ? 'active' : ''}
                        style={{
                            '--config-primary': theme.primary || '#4F46E5',
                            '--config-soft': theme.soft || '#EEF2FF',
                        }}
                    >
                        <div className="sv-config-colorbar"/>
                        <small>{config.industry}</small>
                        <h3>{config.name}</h3>
                        <p>{config.description}</p>
                        <strong>Mulai {rupiah(starterTotal)}/bulan</strong>
                        <div>{(config.pipeline || []).map((stage) => <i key={stage.key}>{stage.label}</i>)}</div>
                        {active && <span className="sv-active-config-chip">ACTIVE</span>}
                    </article>;
                })}
            </div>
        </section>
    </AppLayout>;
}
