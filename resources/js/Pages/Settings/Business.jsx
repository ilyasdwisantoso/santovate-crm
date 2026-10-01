import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const rupiah = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
const cap=(r)=>r?.unlimited?'Tidak terbatas':`${Number(r?.used||0).toLocaleString('id-ID')} / ${Number(r?.limit||0).toLocaleString('id-ID')}`;

export default function Business({ organization, activeSubscription, entitlements, configurations, plans }) {
    const users=entitlements?.limits?.users;
    const prospects=entitlements?.limits?.prospects;
    return <AppLayout title="Business Configuration" subtitle="Workflow, entitlement, pipeline dan harga CRM mengikuti subscription aktif workspace.">
        <Head title="Business Configuration"/>

        <section className="panel">
            <div className="panel-head"><div><span className="eyebrow">Active workspace</span><h3>{organization.name}</h3><p>{organization.business_configuration?.name || 'Belum aktif'}</p></div><span className="badge badge-violet">{activeSubscription?.plan?.name || 'No active plan'}</span></div>
            {activeSubscription && <div className="sv-admin-summary"><span><small>Billing</small><strong>{activeSubscription.billing_cycle}</strong></span><span><small>Berlaku sampai</small><strong>{activeSubscription.ends_at ? new Date(activeSubscription.ends_at).toLocaleDateString('id-ID') : '-'}</strong></span><span><small>User aktif</small><strong>{cap(users)}</strong></span><span><small>Prospek</small><strong>{cap(prospects)}</strong></span></div>}
        </section>

        {entitlements&&<section className="panel"><div className="panel-head"><div><span className="eyebrow">Effective entitlement</span><h3>Kapasitas & fitur aktual</h3><p>Hasil gabungan plan, subscription override, business configuration, dan grant/add-on aktif.</p></div><span className="badge badge-info">{entitlements.enabled_features?.length||0} fitur aktif</span></div><div className="entitlement-grid"><article><span>User aktif</span><strong>{cap(users)}</strong><small>{users?.unlimited?'Unlimited':`${users?.remaining||0} tersisa`}</small><div className="entitlement-meter"><i style={{width:`${users?.usage_percent||0}%`}}/></div></article><article><span>Prospek</span><strong>{cap(prospects)}</strong><small>{prospects?.unlimited?'Unlimited':`${Number(prospects?.remaining||0).toLocaleString('id-ID')} tersisa`}</small><div className="entitlement-meter"><i style={{width:`${prospects?.usage_percent||0}%`}}/></div></article><article><span>Grant aktif</span><strong>{entitlements.grants?.active_count||0}</strong><small>Fondasi add-on / override</small></article></div><div className="entitlement-features">{Object.entries(entitlements.features||{}).map(([key,on])=><span key={key} className={on?'on':'off'}>{on?'✓':'×'} {key.replaceAll('_',' ')}</span>)}</div></section>}

        <section className="panel"><div className="panel-head"><div><span className="eyebrow">Configuration library</span><h3>Demo seluruh konfigurasi</h3><p>Warna dashboard dan workflow berubah mengikuti konfigurasi aktif.</p></div><Link href="/plans" className="btn btn-primary">Lihat Paket & Harga</Link></div><div className="sv-admin-config-grid sv-admin-config-grid-v402">{configurations.map((config) => {const theme=config.theme||{};const active=organization.business_configuration?.id===config.id;const starter=plans?.find((plan)=>plan.key==='starter')||plans?.[0];const starterTotal=Number(starter?.monthly_price||0)+Number(config.monthly_addon_price||0);return <article key={config.id} className={active?'active':''} style={{'--config-primary':theme.primary||'#4F46E5','--config-soft':theme.soft||'#EEF2FF'}}><div className="sv-config-colorbar"/><small>{config.industry}</small><h3>{config.name}</h3><p>{config.description}</p><strong>Mulai {rupiah(starterTotal)}/bulan</strong><div>{(config.pipeline||[]).map((stage)=><i key={stage.key}>{stage.label}</i>)}</div>{active&&<span className="sv-active-config-chip">ACTIVE</span>}</article>;})}</div></section>
    </AppLayout>;
}
