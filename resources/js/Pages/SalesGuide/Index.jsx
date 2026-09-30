import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Badge } from '../../Components/Ui';
import { money } from '../../Utils/format';

const featureLabel = (value='') => value.replaceAll('_',' ').replace(/\b\w/g, c=>c.toUpperCase());
const priceLabel = (value) => Number(value || 0) > 0 ? money(value) : 'Belum diset';

function SolutionEditor({ item, onClose }) {
    const editing = Boolean(item?.id);
    const form = useForm({
        code:item?.code||'', category:item?.category||'Custom Extension', name:item?.name||'',
        description:item?.description||'', pricing_model:item?.pricing_model||'custom', unit:item?.unit||'project',
        internal_cost:item?.internal_cost??0, recommended_price:item?.recommended_price??0,
        minimum_price:item?.minimum_price??'', dependencies_text:(item?.dependencies||[]).join(', '),
        upsell_notes:item?.upsell_notes||'', sales_notes:item?.sales_notes||'', is_active:item?.is_active??true,
        sort_order:item?.sort_order??0,
    });
    const submit=(e)=>{e.preventDefault();const options={preserveScroll:true,onSuccess:onClose};editing?form.put(`/sales-guide/solutions/${item.id}`,options):form.post('/sales-guide/solutions',options)};
    return <div className="modal-backdrop" onClick={onClose}><form className="modal-card sv-guide-editor" onClick={e=>e.stopPropagation()} onSubmit={submit}>
        <div className="modal-head"><div><span className="eyebrow">Internal pricebook</span><h3>{editing?'Edit extension':'Tambah extension'}</h3><p>Cost bersifat internal dan tidak ikut tercetak ke quotation client.</p></div><button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={19}/></button></div>
        <div className="form-grid">
            <label className="field"><span>Code *</span><input value={form.data.code} onChange={e=>form.setData('code',e.target.value)} placeholder="EXT-LOG-ORDER"/></label>
            <label className="field"><span>Category *</span><input value={form.data.category} onChange={e=>form.setData('category',e.target.value)} placeholder="Logistics Extension"/></label>
            <label className="field span-2"><span>Nama *</span><input value={form.data.name} onChange={e=>form.setData('name',e.target.value)} placeholder="Create Order"/></label>
            <label className="field"><span>Pricing</span><select value={form.data.pricing_model} onChange={e=>form.setData('pricing_model',e.target.value)}><option value="custom">Custom</option><option value="one_time">One-time</option><option value="recurring">Recurring</option></select></label>
            <label className="field"><span>Unit</span><input value={form.data.unit} onChange={e=>form.setData('unit',e.target.value)} placeholder="project"/></label>
            <label className="field"><span>Internal Cost</span><input type="number" min="0" value={form.data.internal_cost} onChange={e=>form.setData('internal_cost',e.target.value)}/><small className="field-hint">{money(form.data.internal_cost)}</small></label>
            <label className="field"><span>Recommended Selling</span><input type="number" min="0" value={form.data.recommended_price} onChange={e=>form.setData('recommended_price',e.target.value)}/><small className="field-hint">{money(form.data.recommended_price)}</small></label>
            <label className="field"><span>Minimum Selling</span><input type="number" min="0" value={form.data.minimum_price} onChange={e=>form.setData('minimum_price',e.target.value)}/><small className="field-hint">Approval dipicu bila quotation di bawah floor.</small></label>
            <label className="field"><span>Sort Order</span><input type="number" min="0" value={form.data.sort_order} onChange={e=>form.setData('sort_order',e.target.value)}/></label>
            <label className="field span-2"><span>Scope / Description</span><textarea rows="3" value={form.data.description} onChange={e=>form.setData('description',e.target.value)}/></label>
            <label className="field span-2"><span>Dependencies</span><input value={form.data.dependencies_text} onChange={e=>form.setData('dependencies_text',e.target.value)} placeholder="Create Order, Shipment Tracking"/></label>
            <label className="field span-2"><span>Upsell Notes</span><textarea rows="2" value={form.data.upsell_notes} onChange={e=>form.setData('upsell_notes',e.target.value)}/></label>
            <label className="field span-2"><span>Sales Notes</span><textarea rows="2" value={form.data.sales_notes} onChange={e=>form.setData('sales_notes',e.target.value)}/></label>
            <label className="filter-check span-2"><input type="checkbox" checked={form.data.is_active} onChange={e=>form.setData('is_active',e.target.checked)}/><span>Aktif dan dapat dipilih Sales</span></label>
        </div>
        {Object.keys(form.errors).length>0&&<div className="alert alert-error">{Object.values(form.errors)[0]}</div>}
        <div className="modal-actions"><button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button><button className="btn btn-primary" disabled={form.processing}>{editing?'Simpan':'Tambah Extension'}</button></div>
    </form></div>;
}

export default function SalesGuide({ plans=[], configurations=[], solutions=[], concepts=[], sales_flow=[], can_manage_catalog=false, last_reviewed }) {
    const [editing,setEditing]=useState(null);
    const [category,setCategory]=useState('all');
    const categories=useMemo(()=>['all',...new Set(solutions.map(x=>x.category))],[solutions]);
    const filtered=category==='all'?solutions:solutions.filter(x=>x.category===category);

    return <AppLayout title="Panduan Sales" subtitle="Alur kerja, istilah, subscription, business configuration, dan internal extension pricebook." action={can_manage_catalog?<button className="btn btn-primary" onClick={()=>setEditing({})}><Icon name="plus" size={16}/>Extension</button>:null}>
        <Head title="Panduan Sales"/>

        <section className="sv-guide-hero panel">
            <div><span className="eyebrow">Sales playbook</span><h2>Dari kebutuhan client sampai pembayaran, tanpa menebak field CRM.</h2><p>Gunakan alur ini sebagai urutan kerja. Subscription bersifat fixed; upselling custom terjadi melalui extension/integration setelah discovery.</p></div>
            <Link href="/opportunities/create" className="btn btn-primary">Mulai Opportunity <Icon name="chevron" size={15}/></Link>
        </section>

        <section className="sv-flow-grid">
            {sales_flow.map((step,index)=><article className="sv-flow-card" key={step.key}><span>{String(index+1).padStart(2,'0')}</span><strong>{step.label}</strong><small>{step.short}</small><p>{step.description}</p></article>)}
        </section>

        <section className="panel sv-guide-section">
            <div className="panel-head"><div><span className="eyebrow">Subscription</span><h3>Harga langganan tetap</h3><p>Harga diambil dari database aktif dan sama dengan public pricing.</p></div><Link className="text-link" href="/pricing">Public pricing <Icon name="chevron" size={14}/></Link></div>
            <div className="sv-guide-plan-grid">{plans.map(plan=><article key={plan.id}><span>{plan.name}</span><strong>{money(plan.monthly_price)}<small>/bulan</small></strong><em>{money(plan.annual_price)} / tahun</em><p>{plan.user_limit} user · {Number(plan.prospect_limit||0).toLocaleString('id-ID')} prospect</p><div>{(plan.features||[]).slice(0,6).map(f=><Badge key={f}>{featureLabel(f)}</Badge>)}</div></article>)}</div>
        </section>

        <section className="panel sv-guide-section">
            <div className="panel-head"><div><span className="eyebrow">Business configuration</span><h3>Workflow industri</h3><p>Configuration menyesuaikan workflow CRM; bukan custom development.</p></div></div>
            <div className="sv-config-mini-grid">{configurations.map(config=><article key={config.id}><strong>{config.name}</strong><small>{config.industry}</small><p>{config.description}</p><span>+ {money(config.monthly_price)} / bulan</span></article>)}</div>
        </section>

        <section className="panel sv-guide-section" id="solution-pricebook">
            <div className="panel-head"><div><span className="eyebrow">Internal only</span><h3>Solution / Extension Pricebook</h3><p>Cost dan price reference untuk upselling custom setelah kebutuhan client ditemukan.</p></div><div className="sv-category-tabs">{categories.map(c=><button key={c} className={category===c?'active':''} onClick={()=>setCategory(c)}>{c==='all'?'Semua':c}</button>)}</div></div>
            <div className="sv-solution-grid">{filtered.map(item=><article className={!item.is_active?'is-inactive':''} key={item.id}>
                <div className="sv-solution-head"><span>{item.code}</span><Badge tone={item.is_active?'success':'neutral'}>{item.is_active?'Aktif':'Nonaktif'}</Badge></div>
                <h3>{item.name}</h3><small>{item.category} · {item.pricing_model.replace('_',' ')}</small><p>{item.description||'Scope belum ditulis.'}</p>
                <div className="sv-solution-money"><div><span>Cost</span><strong>{priceLabel(item.internal_cost)}</strong></div><div><span>Recommended</span><strong>{priceLabel(item.recommended_price)}</strong></div><div><span>Minimum</span><strong>{item.minimum_price?money(item.minimum_price):'—'}</strong></div></div>
                {item.gross_margin!==null&&<div className="sv-margin-chip">Reference gross margin {item.gross_margin}%</div>}
                {item.dependencies?.length>0&&<div className="sv-guide-note"><b>Dependency</b><span>{item.dependencies.join(' · ')}</span></div>}
                {item.upsell_notes&&<div className="sv-guide-note"><b>Upsell</b><span>{item.upsell_notes}</span></div>}
                {item.sales_notes&&<div className="sv-guide-note subtle"><b>Sales note</b><span>{item.sales_notes}</span></div>}
                {can_manage_catalog&&<button className="btn btn-soft btn-sm" onClick={()=>setEditing(item)}><Icon name="edit" size={14}/>Edit pricebook</button>}
            </article>)}</div>
        </section>

        <section className="panel sv-guide-section" id="finance">
            <div className="panel-head"><div><span className="eyebrow">Glossary</span><h3>Istilah yang sering membingungkan</h3><p>Definisi singkat yang sama dipakai di Opportunity, Quotation, dan Finance.</p></div></div>
            <div className="sv-glossary-grid">{concepts.map(item=><article key={item.key}><strong>{item.title}</strong><p>{item.plain}</p><small><b>Contoh:</b> {item.example}</small><small><b>Jangan salah:</b> {item.avoid}</small></article>)}</div>
        </section>

        <p className="sv-guide-reviewed">Last reviewed: {last_reviewed}</p>
        {editing&&<SolutionEditor item={editing} onClose={()=>setEditing(null)}/>} 
    </AppLayout>;
}
