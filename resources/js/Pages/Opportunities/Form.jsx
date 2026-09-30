import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Badge } from '../../Components/Ui';
import { money } from '../../Utils/format';

const dt=v=>v?new Date(v).toISOString().slice(0,16):'';
function Field({label,help,error,span=false,children}){return <label className={`field ${span?'span-2':''}`}><span>{label}</span>{help&&<small className="field-help">{help}</small>}{children}{error&&<small className="field-error">{error}</small>}</label>}

export default function Form({mode,opportunity,prospects,salesUsers,stages,statuses,lostReasons,solutionCatalog=[]}){
    const editing=mode==='edit';
    const [catalogOpen,setCatalogOpen]=useState(true);
    const f=useForm({
        prospect_id:opportunity.prospect_id||'',owner_id:opportunity.owner_id||'',name:opportunity.name||'',status:opportunity.status||'open',stage:opportunity.stage||'qualification',
        business_problem:opportunity.business_problem||'',current_process:opportunity.current_process||'',required_solution:opportunity.required_solution||'',required_features:opportunity.required_features||'',
        estimated_users:opportunity.estimated_users||'',budget:opportunity.budget??'',expected_value:opportunity.expected_value??0,probability:opportunity.probability??10,target_go_live:opportunity.target_go_live||'',
        decision_maker:opportunity.decision_maker||'',decision_process:opportunity.decision_process||'',urgency:opportunity.urgency||'',next_action:opportunity.next_action||'',next_follow_up_at:dt(opportunity.next_follow_up_at),
        lost_reason:opportunity.lost_reason||'',competitor:opportunity.competitor||'',recontact_at:dt(opportunity.recontact_at),solution_item_ids:(opportunity.solution_item_ids||[]).map(Number),
    });
    const selected=useMemo(()=>solutionCatalog.filter(x=>f.data.solution_item_ids.includes(Number(x.id))),[solutionCatalog,f.data.solution_item_ids]);
    const referenceValue=selected.reduce((sum,x)=>sum+Number(x.recommended_price||0),0);
    const toggle=(id)=>{id=Number(id);f.setData('solution_item_ids',f.data.solution_item_ids.includes(id)?f.data.solution_item_ids.filter(x=>x!==id):[...f.data.solution_item_ids,id])};
    const submit=e=>{e.preventDefault();editing?f.put(`/opportunities/${opportunity.id}`):f.post('/opportunities')};

    return <AppLayout title={editing?'Edit Opportunity':'Buat Opportunity'} subtitle="Guided discovery: pahami kebutuhan client sebelum menentukan solution dan quotation." action={<div className="action-group"><Link href="/sales-guide" className="btn btn-soft">Panduan</Link><Link href={editing?`/opportunities/${opportunity.id}`:'/opportunities'} className="btn btn-secondary"><Icon name="arrowLeft" size={17}/>Kembali</Link></div>}>
        <Head title={editing?'Edit Opportunity':'Buat Opportunity'}/>
        <section className="sv-context-banner sv-context-guide"><span className="sv-context-icon"><Icon name="spark" size={19}/></span><div><strong>Kapan form ini dipakai?</strong><p>Gunakan setelah prospek punya kebutuhan/project yang cukup nyata. Fokus pada masalah, proses sekarang, target hasil, solution yang relevan, budget, decision maker, dan next step.</p></div></section>
        <form onSubmit={submit} className="form-layout sv-guided-form"><div className="form-main">
            <section className="panel form-section"><div className="section-head"><span className="section-number">01</span><div><h3>Context</h3><p>Siapa client dan peluang apa yang sedang dikerjakan.</p></div></div><div className="form-grid">
                <Field label="Prospek *" help="Perusahaan/client yang mempunyai peluang ini." error={f.errors.prospect_id}><select value={f.data.prospect_id} onChange={e=>f.setData('prospect_id',e.target.value)}><option value="">Pilih prospek</option>{prospects.map(p=><option value={p.id} key={p.id}>{p.company_name}</option>)}</select></Field>
                <Field label="Nama Opportunity *" help="Gunakan nama project/upsell yang mudah dikenali." error={f.errors.name}><input value={f.data.name} onChange={e=>f.setData('name',e.target.value)} placeholder="Logistics Tracking & Customer Portal"/></Field>
                <Field label="Owner" help="Sales/AE yang bertanggung jawab."><select value={f.data.owner_id} onChange={e=>f.setData('owner_id',e.target.value)}><option value="">Belum diassign</option>{salesUsers.map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
                <Field label="Stage" help="Posisi opportunity dalam proses penjualan."><select value={f.data.stage} onChange={e=>f.setData('stage',e.target.value)}>{Object.entries(stages).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field>
                <Field label="Status"><select value={f.data.status} onChange={e=>f.setData('status',e.target.value)}>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field>
                <Field label="Urgency" help="Seberapa cepat client perlu solusi."><select value={f.data.urgency} onChange={e=>f.setData('urgency',e.target.value)}><option value="">Belum diketahui</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></Field>
            </div></section>

            <section className="panel form-section"><div className="section-head"><span className="section-number">02</span><div><h3>Discovery</h3><p>Tulis dengan bahasa bisnis client, bukan istilah internal Santovate.</p></div></div><div className="form-grid">
                <Field label="Masalah utama" help="Contoh: order masih manual via WhatsApp/Excel sehingga tracking tidak konsisten." span><textarea rows="3" value={f.data.business_problem} onChange={e=>f.setData('business_problem',e.target.value)} placeholder="Apa masalah yang membuat client mencari solusi?"/></Field>
                <Field label="Proses / sistem sekarang" help="Contoh: Sales menerima order WA → input Excel → update status manual." span><textarea rows="3" value={f.data.current_process} onChange={e=>f.setData('current_process',e.target.value)} placeholder="Bagaimana pekerjaan dilakukan sekarang?"/></Field>
                <Field label="Target hasil client" help="Contoh: order dibuat di CRM dan customer bisa tracking sendiri." span><textarea rows="3" value={f.data.required_solution} onChange={e=>f.setData('required_solution',e.target.value)} placeholder="Apa hasil yang ingin dicapai client?"/></Field>
                <Field label="Requirement khusus yang belum ada di catalog" help="Tulis kebutuhan bespoke, rule, atau constraint teknis." span><textarea rows="3" value={f.data.required_features} onChange={e=>f.setData('required_features',e.target.value)} placeholder="Contoh: approval 2 level, custom API vendor, SLA khusus..."/></Field>
            </div></section>

            <section className="panel form-section"><div className="panel-head"><div><span className="eyebrow">Solution mapping</span><h3>Pilih extension yang relevan</h3><p>Pilihan ini akan menjadi referensi saat membuat quotation.</p></div><button type="button" className="btn btn-soft btn-sm" onClick={()=>setCatalogOpen(!catalogOpen)}>{catalogOpen?'Ringkas':'Tampilkan Catalog'}</button></div>
                {catalogOpen&&<div className="sv-discovery-catalog">{solutionCatalog.map(item=>{const active=f.data.solution_item_ids.includes(Number(item.id));return <button type="button" className={`sv-discovery-option ${active?'selected':''}`} onClick={()=>toggle(item.id)} key={item.id}><span className="sv-discovery-check">{active?<Icon name="check" size={14}/>:<Icon name="plus" size={14}/>}</span><div><small>{item.category}</small><strong>{item.name}</strong><p>{item.description}</p><div className="sv-option-price"><span>Cost {Number(item.internal_cost)>0?money(item.internal_cost):'belum diset'}</span><span>Sell {Number(item.recommended_price)>0?money(item.recommended_price):'belum diset'}</span></div>{item.upsell_notes&&<em>{item.upsell_notes}</em>}</div></button>})}</div>}
                {selected.length>0&&<div className="sv-selected-solutions"><strong>{selected.length} solution dipilih</strong><div>{selected.map(x=><Badge key={x.id} tone="info">{x.name}</Badge>)}</div></div>}
            </section>

            <section className="panel form-section"><div className="section-head"><span className="section-number">03</span><div><h3>Commercial Fit</h3><p>Nilai awal untuk forecasting; quotation final boleh berubah setelah scope dikunci.</p></div></div><div className="form-grid">
                <Field label="Budget Client" help="Isi jika client sudah menyebut budget."><input type="number" min="0" value={f.data.budget} onChange={e=>f.setData('budget',e.target.value)}/><small className="field-hint">{money(f.data.budget)}</small></Field>
                <Field label="Expected Value *" help="Estimasi nilai jual jika opportunity berhasil." error={f.errors.expected_value}><input type="number" min="0" value={f.data.expected_value} onChange={e=>f.setData('expected_value',e.target.value)}/><small className="field-hint">{money(f.data.expected_value)}</small>{referenceValue>0&&<button type="button" className="field-inline-action" onClick={()=>f.setData('expected_value',referenceValue)}>Gunakan catalog reference {money(referenceValue)}</button>}</Field>
                <Field label="Probability %" help="Confidence saat ini, bukan angka pasti."><input type="number" min="0" max="100" value={f.data.probability} onChange={e=>f.setData('probability',e.target.value)}/></Field>
                <Field label="Estimated Users"><input type="number" min="1" value={f.data.estimated_users} onChange={e=>f.setData('estimated_users',e.target.value)}/></Field>
                <Field label="Target Go-Live"><input type="date" value={f.data.target_go_live} onChange={e=>f.setData('target_go_live',e.target.value)}/></Field>
                <Field label="Decision Maker" help="Orang yang punya otoritas menyetujui pembelian."><input value={f.data.decision_maker} onChange={e=>f.setData('decision_maker',e.target.value)} placeholder="Nama / jabatan"/></Field>
                <Field label="Decision Process" span help="Contoh: user demo → Ops Manager review → Director approve."><textarea rows="2" value={f.data.decision_process} onChange={e=>f.setData('decision_process',e.target.value)}/></Field>
            </div></section>

            <section className="panel form-section"><div className="section-head"><span className="section-number">04</span><div><h3>Next Step</h3><p>Opportunity tanpa next action mudah menjadi pipeline pasif.</p></div></div><div className="form-grid">
                <Field label="Next Action" help="Apa tindakan Sales berikutnya?"><input value={f.data.next_action} onChange={e=>f.setData('next_action',e.target.value)} placeholder="Demo tracking / technical discovery / kirim quotation"/></Field>
                <Field label="Next Follow-up"><input type="datetime-local" value={f.data.next_follow_up_at} onChange={e=>f.setData('next_follow_up_at',e.target.value)}/></Field>
                {f.data.status==='lost'&&<><Field label="Lost Reason"><select value={f.data.lost_reason} onChange={e=>f.setData('lost_reason',e.target.value)}><option value="">Pilih alasan</option>{Object.entries(lostReasons||{}).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field><Field label="Competitor"><input value={f.data.competitor} onChange={e=>f.setData('competitor',e.target.value)}/></Field><Field label="Recontact Date"><input type="datetime-local" value={f.data.recontact_at} onChange={e=>f.setData('recontact_at',e.target.value)}/></Field></>}
            </div></section>
        </div><aside className="form-side"><section className="panel sv-form-summary"><span className="eyebrow">Opportunity snapshot</span><div><span>Selected solution</span><strong>{selected.length}</strong></div><div><span>Catalog reference</span><strong>{money(referenceValue)}</strong></div><div><span>Expected value</span><strong>{money(f.data.expected_value)}</strong></div><div><span>Weighted pipeline</span><strong>{money(Number(f.data.expected_value||0)*Number(f.data.probability||0)/100)}</strong></div><p>Catalog reference hanya panduan. Quotation final ditentukan setelah scope dan commercial terms dikonfirmasi.</p></section><button disabled={f.processing} className="btn btn-primary btn-block btn-lg">{f.processing?'Menyimpan...':editing?'Simpan Opportunity':'Buat Opportunity'}</button></aside></form>
    </AppLayout>;
}
