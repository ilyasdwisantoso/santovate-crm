import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { EmptyState, PriorityBadge } from '../../Components/Ui';

const tabs = {
  response_needed: 'Perlu Balas',
  lead_age_due: 'Wajib Feedback',
  no_reply: 'Belum Ada Feedback',
  scheduled: 'Terjadwal',
};
const waNumber = (v='') => { let d=String(v).replace(/\D/g,''); if(d.startsWith('0')) d=`62${d.slice(1)}`; if(d.startsWith('8')) d=`62${d}`; return d; };

function Composer({ item, queueType, templates, apiReady, close }) {
  const suggested = item.suggested_template_id ? String(item.suggested_template_id) : '';
  const [templateId,setTemplateId] = useState(suggested);
  const [opened,setOpened] = useState(false);
  const form = useForm({ template_id:suggested, message:item.suggested_message||'', queue_type:queueType, feedback_note:'', next_follow_up_days:queueType==='lead_age_due'?3:5 });
  const selected = templates.find(t=>String(t.id)===String(templateId));
  const choose = e => { const id=e.target.value; setTemplateId(id); form.setData('template_id',id); if(id && item.template_messages?.[id] !== undefined) form.setData('message',item.template_messages[id]); };
  const manual = () => { const n=waNumber(item.phone); if(!n || !form.data.message.trim()) return; window.open(`https://wa.me/${n}?text=${encodeURIComponent(form.data.message.trim())}`,'_blank','noopener,noreferrer'); setOpened(true); };
  const cloud = () => form.post(`/follow-ups/${item.id}/cloud-send`,{preserveScroll:true,onSuccess:close});
  const mark = () => form.post(`/follow-ups/${item.id}/sent`,{preserveScroll:true,onSuccess:close});
  return <div className="modal-backdrop" onClick={close}><section className="followup-composer" onClick={e=>e.stopPropagation()}>
    <div className="modal-head"><div><span className="eyebrow">WhatsApp follow-up</span><h3>{item.company_name}</h3><p>{item.contact_name||'PIC'} · {item.phone||'Nomor belum ada'}</p></div><button className="icon-button" onClick={close}><Icon name="close"/></button></div>
    <div className="sv-wa-status-row"><span>Nomor <b>{item.whatsapp_status||'unknown'}</b></span><span>Opt-in <b>{item.whatsapp_opted_in?'YES':'NO'}</b></span><span>Channel <b>{apiReady?'Cloud API + manual':'Manual wa.me'}</b></span></div>
    <label className="field"><span>Template</span><select value={templateId} onChange={choose}><option value="">Draft</option>{templates.map(t=><option value={t.id} key={t.id}>{t.name} · {t.meta_status}</option>)}</select></label>
    {selected?.image_url && <img className="sv-template-image" src={selected.image_url} alt="Template header"/>}
    <label className="field"><span>Review pesan</span><textarea rows="9" value={form.data.message} onChange={e=>form.setData('message',e.target.value)}/></label>
    {queueType==='lead_age_due' && <label className="field"><span>Feedback AE * wajib</span><textarea rows="3" value={form.data.feedback_note} onChange={e=>form.setData('feedback_note',e.target.value)}/></label>}
    <label className="field"><span>Reminder berikutnya</span><select value={form.data.next_follow_up_days} onChange={e=>form.setData('next_follow_up_days',Number(e.target.value))}>{[1,3,5,7,14,0].map(x=><option value={x} key={x}>{x?`${x} hari`:'Tidak dijadwalkan'}</option>)}</select></label>
    {Object.values(form.errors).length>0 && <div className="alert alert-error">{Object.values(form.errors)[0]}</div>}
    <div className="modal-actions"><button className="btn btn-secondary" onClick={manual}><Icon name="whatsapp" size={16}/>Buka WhatsApp</button>{apiReady&&<button className="btn btn-primary" onClick={cloud} disabled={!templateId||form.processing||(queueType==='lead_age_due'&&!form.data.feedback_note.trim())}>Kirim Cloud API</button>}<button className="btn btn-secondary" onClick={mark} disabled={!opened||form.processing||(queueType==='lead_age_due'&&!form.data.feedback_note.trim())}>Tandai Manual Terkirim</button></div>
  </section></div>;
}

function TemplateEditor({ template, close }) {
  const [params,setParams]=useState((template.body_parameters||[]).join(', '));
  const form=useForm({name:template.name,wait_days:template.wait_days,message:template.message,header_type:template.header_type||'none',image_url:template.image_url||'',meta_template_name:template.meta_template_name||'',meta_language:template.meta_language||'id',meta_status:template.meta_status||'draft',meta_category:template.meta_category||'marketing',body_parameters:template.body_parameters||[]});
  const submit=e=>{e.preventDefault();form.transform(data=>({...data,body_parameters:params.split(',').map(x=>x.trim()).filter(Boolean)})).put(`/follow-up-templates/${template.id}`,{preserveScroll:true,onSuccess:close});};
  return <div className="modal-backdrop" onClick={close}><form className="followup-composer" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="modal-head"><div><span className="eyebrow">Template + Meta</span><h3>{template.name}</h3></div><button type="button" className="icon-button" onClick={close}><Icon name="close"/></button></div><div className="form-grid two"><label className="field"><span>Nama</span><input value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/></label><label className="field"><span>Jeda hari</span><input type="number" value={form.data.wait_days} onChange={e=>form.setData('wait_days',Number(e.target.value))}/></label><label className="field"><span>Header</span><select value={form.data.header_type} onChange={e=>form.setData('header_type',e.target.value)}><option value="none">None</option><option value="image">Image</option></select></label><label className="field"><span>Image URL</span><input value={form.data.image_url} onChange={e=>form.setData('image_url',e.target.value)}/></label><label className="field"><span>Meta template name</span><input value={form.data.meta_template_name} onChange={e=>form.setData('meta_template_name',e.target.value)}/></label><label className="field"><span>Meta status</span><select value={form.data.meta_status} onChange={e=>form.setData('meta_status',e.target.value)}><option value="draft">Draft</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label></div><label className="field"><span>Body parameters (comma separated)</span><input value={params} onChange={e=>setParams(e.target.value)}/></label><label className="field"><span>Pesan draft/manual</span><textarea rows="8" value={form.data.message} onChange={e=>form.setData('message',e.target.value)}/></label><button className="btn btn-primary">Simpan Template</button></form></div>;
}

export default function FollowUpsIndex({ queues, stats, templates, noReplyDays, leadAgeDays, whatsappApiReady }) {
  const { auth }=usePage().props; const [tab,setTab]=useState(stats.response_needed?'response_needed':stats.lead_age_due?'lead_age_due':stats.no_reply?'no_reply':'scheduled'); const [composer,setComposer]=useState(null); const [editing,setEditing]=useState(null); const items=queues[tab]||[];
  return <AppLayout title="Follow Up" subtitle="Queue, template, WhatsApp Cloud API dan manual fallback."><Head title="Follow Up"/><section className="followup-summary-grid">{Object.keys(tabs).map(k=><article className="followup-summary-card" key={k}><small>{tabs[k]}{k==='lead_age_due'?` H-${leadAgeDays}`:''}</small><strong>{stats[k]||0}</strong><p>{k==='no_reply'?`${noReplyDays}+ hari tanpa respons`:'Daily work queue'}</p></article>)}</section><section className="panel"><div className="followup-tabs">{Object.keys(tabs).map(k=><button className={tab===k?'active':''} onClick={()=>setTab(k)} key={k}><span>{tabs[k]}</span><b>{stats[k]||0}</b></button>)}</div>{items.length?<div className="sv-follow-grid">{items.map(p=><article key={p.id}><div><Link href={`/prospects/${p.id}`}><strong>{p.company_name}</strong></Link><small>{p.contact_name||'PIC belum ada'} · {p.phone||'WA belum ada'}</small></div><PriorityBadge priority={p.priority}/><p>{p.suggested_message||'Pilih template saat review.'}</p><button className="btn btn-primary" onClick={()=>setComposer({item:p,type:tab})}><Icon name="whatsapp" size={16}/>Review & Follow-up</button></article>)}</div>:<EmptyState icon="check" title="Queue bersih" description="Tidak ada tindakan pada kategori ini."/>}</section><section className="panel"><div className="panel-head"><div><span className="eyebrow">Template library</span><h3>WhatsApp template</h3></div></div><div className="template-card-grid">{templates.map(t=><article className="template-card" key={t.id}>{t.image_url&&<img className="sv-template-thumb" src={t.image_url} alt=""/>}<strong>{t.name}</strong><small>{t.meta_template_name||'Manual draft'} · {t.meta_status}</small><p>{t.message}</p>{auth.user.is_admin&&<button className="btn btn-secondary" onClick={()=>setEditing(t)}>Edit</button>}</article>)}</div></section>{composer&&<Composer item={composer.item} queueType={composer.type} templates={templates} apiReady={whatsappApiReady} close={()=>setComposer(null)}/>} {editing&&<TemplateEditor template={editing} close={()=>setEditing(null)}/>}</AppLayout>;
}
