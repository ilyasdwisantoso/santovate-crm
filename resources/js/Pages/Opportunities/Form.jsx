import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { money } from '../../Utils/format';
const dt=v=>v?new Date(v).toISOString().slice(0,16):'';
function Field({label,error,span=false,children}){return <label className={`field ${span?'span-2':''}`}><span>{label}</span>{children}{error&&<small className="field-error">{error}</small>}</label>}
export default function Form({mode,opportunity,prospects,salesUsers,stages,statuses,lostReasons}){
 const editing=mode==='edit';
 const f=useForm({prospect_id:opportunity.prospect_id||'',owner_id:opportunity.owner_id||'',name:opportunity.name||'',status:opportunity.status||'open',stage:opportunity.stage||'qualification',business_problem:opportunity.business_problem||'',current_process:opportunity.current_process||'',required_solution:opportunity.required_solution||'',required_features:opportunity.required_features||'',estimated_users:opportunity.estimated_users||'',budget:opportunity.budget??'',expected_value:opportunity.expected_value??0,probability:opportunity.probability??10,target_go_live:opportunity.target_go_live||'',decision_maker:opportunity.decision_maker||'',decision_process:opportunity.decision_process||'',urgency:opportunity.urgency||'',next_action:opportunity.next_action||'',next_follow_up_at:dt(opportunity.next_follow_up_at),lost_reason:opportunity.lost_reason||'',competitor:opportunity.competitor||'',recontact_at:dt(opportunity.recontact_at)});
 const submit=e=>{e.preventDefault();editing?f.put(`/opportunities/${opportunity.id}`):f.post('/opportunities')};
 return <AppLayout title={editing?'Edit Opportunity':'Buat Opportunity'} subtitle="Pisahkan peluang komersial dari record prospek agar satu account bisa memiliki beberapa project/upsell." action={<Link href={editing?`/opportunities/${opportunity.id}`:'/opportunities'} className="btn btn-secondary"><Icon name="arrowLeft" size={17}/>Kembali</Link>}>
  <Head title={editing?'Edit Opportunity':'Buat Opportunity'}/><form onSubmit={submit} className="form-layout"><div className="form-main">
   <section className="panel form-section"><div className="section-head"><span className="section-number">01</span><div><h3>Opportunity</h3><p>Account, owner, stage dan nilai peluang.</p></div></div><div className="form-grid">
    <Field label="Prospek *" error={f.errors.prospect_id}><select value={f.data.prospect_id} onChange={e=>f.setData('prospect_id',e.target.value)}><option value="">Pilih prospek</option>{prospects.map(p=><option value={p.id} key={p.id}>{p.company_name}</option>)}</select></Field>
    <Field label="Nama Opportunity *" error={f.errors.name}><input value={f.data.name} onChange={e=>f.setData('name',e.target.value)} placeholder="Custom CRM / Website / Integration"/></Field>
    <Field label="Owner"><select value={f.data.owner_id} onChange={e=>f.setData('owner_id',e.target.value)}><option value="">Belum diassign</option>{salesUsers.map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
    <Field label="Stage"><select value={f.data.stage} onChange={e=>f.setData('stage',e.target.value)}>{Object.entries(stages).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field>
    <Field label="Status"><select value={f.data.status} onChange={e=>f.setData('status',e.target.value)}>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field>
    <Field label="Expected Value" error={f.errors.expected_value}><input type="number" min="0" value={f.data.expected_value} onChange={e=>f.setData('expected_value',e.target.value)}/><small className="field-hint">{money(f.data.expected_value)}</small></Field>
    <Field label="Budget Client"><input type="number" min="0" value={f.data.budget} onChange={e=>f.setData('budget',e.target.value)}/></Field>
    <Field label="Probability %"><input type="number" min="0" max="100" value={f.data.probability} onChange={e=>f.setData('probability',e.target.value)}/></Field>
   </div></section>
   <section className="panel form-section"><div className="section-head"><span className="section-number">02</span><div><h3>Discovery</h3><p>Masalah, proses saat ini, solusi dan requirement.</p></div></div><div className="form-grid">
    <Field label="Main Business Problem" span><textarea rows="4" value={f.data.business_problem} onChange={e=>f.setData('business_problem',e.target.value)}/></Field>
    <Field label="Current Process / System" span><textarea rows="3" value={f.data.current_process} onChange={e=>f.setData('current_process',e.target.value)}/></Field>
    <Field label="Required Solution" span><textarea rows="4" value={f.data.required_solution} onChange={e=>f.setData('required_solution',e.target.value)}/></Field>
    <Field label="Required Features / Modules" span><textarea rows="4" value={f.data.required_features} onChange={e=>f.setData('required_features',e.target.value)} placeholder="CRM, quotation, tracking, dashboard..."/></Field>
    <Field label="Estimated Users"><input type="number" min="1" value={f.data.estimated_users} onChange={e=>f.setData('estimated_users',e.target.value)}/></Field>
    <Field label="Target Go-Live"><input type="date" value={f.data.target_go_live} onChange={e=>f.setData('target_go_live',e.target.value)}/></Field>
   </div></section>
   <section className="panel form-section"><div className="section-head"><span className="section-number">03</span><div><h3>Decision & Next Step</h3><p>Decision maker, urgency dan follow-up wajib.</p></div></div><div className="form-grid">
    <Field label="Decision Maker"><input value={f.data.decision_maker} onChange={e=>f.setData('decision_maker',e.target.value)}/></Field>
    <Field label="Urgency"><select value={f.data.urgency} onChange={e=>f.setData('urgency',e.target.value)}><option value="">Belum diketahui</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></Field>
    <Field label="Decision Process" span><textarea rows="3" value={f.data.decision_process} onChange={e=>f.setData('decision_process',e.target.value)}/></Field>
    <Field label="Next Action"><input value={f.data.next_action} onChange={e=>f.setData('next_action',e.target.value)} placeholder="Schedule demo / kirim proposal"/></Field>
    <Field label="Next Follow-up"><input type="datetime-local" value={f.data.next_follow_up_at} onChange={e=>f.setData('next_follow_up_at',e.target.value)}/></Field>
    {f.data.status==='lost'&&<><Field label="Lost Reason"><select value={f.data.lost_reason} onChange={e=>f.setData('lost_reason',e.target.value)}><option value="">Pilih alasan</option>{Object.entries(lostReasons||{}).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field><Field label="Competitor"><input value={f.data.competitor} onChange={e=>f.setData('competitor',e.target.value)}/></Field><Field label="Recontact Date"><input type="datetime-local" value={f.data.recontact_at} onChange={e=>f.setData('recontact_at',e.target.value)}/></Field></>}
   </div></section>
  </div><aside className="form-side"><section className="panel"><span className="eyebrow">Weighted Pipeline</span><h3 className="side-title">{money(Number(f.data.expected_value||0)*Number(f.data.probability||0)/100)}</h3><p className="muted">Expected Value × Probability.</p></section><button disabled={f.processing} className="btn btn-primary btn-block btn-lg">{f.processing?'Menyimpan...':editing?'Simpan Opportunity':'Buat Opportunity'}</button></aside></form>
 </AppLayout>
}
