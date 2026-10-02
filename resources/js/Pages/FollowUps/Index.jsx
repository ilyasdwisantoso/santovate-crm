import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { EmptyState, PriorityBadge } from '../../Components/Ui';

const tabs = {
  response_needed: 'Perlu Balas',
  lead_age_due: 'Wajib Feedback',
  no_reply: 'Belum Ada Feedback',
  scheduled: 'Terjadwal',
};
const helper = {
  response_needed: 'Customer sudah merespons dan menunggu balasan.',
  lead_age_due: 'Lead melewati batas umur dan wajib diberi feedback.',
  no_reply: 'Follow-up terkirim tetapi belum ada respons customer.',
  scheduled: 'Reminder follow-up yang jatuh tempo hari ini.',
};
const waNumber = (v='') => { let d=String(v).replace(/\D/g,''); if(d.startsWith('0')) d=`62${d.slice(1)}`; if(d.startsWith('8')) d=`62${d}`; return d; };

function FollowUpModalPortal({ children, close }) {
  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.body.classList.add('followup-modal-open-v91');
    const onKeyDown = (event) => { if (event.key === 'Escape') close(); };
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = previousOverflow;
      document.body.classList.remove('followup-modal-open-v91');
    };
  }, [close]);

  if (typeof document === 'undefined') return null;
  return createPortal(children, document.body);
}

function Composer({ item, queueType, templates, apiReady, close }) {
  const suggested = item.suggested_template_id ? String(item.suggested_template_id) : '';
  const [templateId,setTemplateId] = useState(suggested);
  const [opened,setOpened] = useState(false);
  const [manualError,setManualError] = useState('');
  const form = useForm({ template_id:suggested, message:item.suggested_message||'', queue_type:queueType, feedback_note:'', next_follow_up_days:queueType==='lead_age_due'?3:5 });
  const selected = templates.find(t=>String(t.id)===String(templateId));
  const choose = e => { const id=e.target.value; setTemplateId(id); form.setData('template_id',id); setManualError(''); if(id && item.template_messages?.[id] !== undefined) form.setData('message',item.template_messages[id]); };
  const manualPhone = waNumber(item.phone);
  const manual = () => {
    if (!manualPhone) { setManualError('Nomor WhatsApp prospect belum tersedia atau tidak valid. Lengkapi nomor pada data prospect terlebih dahulu.'); return; }
    if (!form.data.message.trim()) { setManualError('Pesan follow-up masih kosong. Pilih template atau isi pesan sebelum membuka WhatsApp.'); return; }
    setManualError('');
    window.open(`https://wa.me/${manualPhone}?text=${encodeURIComponent(form.data.message.trim())}`,'_blank','noopener,noreferrer');
    setOpened(true);
  };
  const cloud = () => form.post(`/follow-ups/${item.id}/cloud-send`,{preserveScroll:true,onSuccess:close});
  const mark = () => form.post(`/follow-ups/${item.id}/sent`,{preserveScroll:true,onSuccess:close});

  return <FollowUpModalPortal close={close}>
    <div className="modal-backdrop followup-modal-backdrop-v91" role="presentation" onClick={close}>
      <section className="followup-composer followup-composer-v7 followup-modal-panel-v91" role="dialog" aria-modal="true" aria-label={`Review follow-up ${item.company_name}`} onClick={e=>e.stopPropagation()}>
        <div className="modal-head"><div><span className="eyebrow">WhatsApp follow-up</span><h3>{item.company_name}</h3><p>{item.contact_name||'PIC'} · {item.phone||'Nomor belum ada'}</p>{item.assigned_user?.name&&<small className="followup-v7-owner-line">Account Executive: <b>{item.assigned_user.name}</b></small>}</div><button type="button" className="icon-button" onClick={close} aria-label="Tutup"><Icon name="close"/></button></div>
        <div className="sv-wa-status-row followup-v7-status-row"><span>Nomor <b>{item.whatsapp_status||'unknown'}</b></span><span>Opt-in <b>{item.whatsapp_opted_in?'YES':'NO'}</b></span><span>Channel <b>{apiReady?'Cloud API + manual':'Manual wa.me'}</b></span></div>
        <label className="field"><span>Template</span><select value={templateId} onChange={choose}><option value="">Draft</option>{templates.map(t=><option value={t.id} key={t.id}>{t.name} · {t.meta_status}</option>)}</select></label>
        {selected?.image_url && <img className="sv-template-image" src={selected.image_url} alt="Template header"/>}
        <label className="field"><span>Review pesan</span><textarea rows="9" value={form.data.message} onChange={e=>{form.setData('message',e.target.value);setManualError('');}}/></label>
        {queueType==='lead_age_due' && <label className="field"><span>Feedback AE * wajib</span><textarea rows="3" value={form.data.feedback_note} onChange={e=>form.setData('feedback_note',e.target.value)}/></label>}
        <label className="field"><span>Reminder berikutnya</span><select value={form.data.next_follow_up_days} onChange={e=>form.setData('next_follow_up_days',Number(e.target.value))}>{[1,3,5,7,14,0].map(x=><option value={x} key={x}>{x?`${x} hari`:'Tidak dijadwalkan'}</option>)}</select></label>
        {manualError&&<div className="alert alert-error followup-modal-error-v91">{manualError}</div>}
        {Object.values(form.errors).length>0 && <div className="alert alert-error">{Object.values(form.errors)[0]}</div>}
        <div className="modal-actions followup-v7-modal-actions">
          <button type="button" className="btn btn-secondary followup-whatsapp-button-v91" onClick={manual}><Icon name="whatsapp" size={16}/>Buka WhatsApp</button>
          {apiReady&&<button type="button" className="btn btn-primary" onClick={cloud} disabled={!templateId||form.processing||(queueType==='lead_age_due'&&!form.data.feedback_note.trim())}>Kirim Cloud API</button>}
          <button type="button" className="btn btn-secondary" onClick={mark} disabled={!opened||form.processing||(queueType==='lead_age_due'&&!form.data.feedback_note.trim())}>Tandai Manual Terkirim</button>
        </div>
      </section>
    </div>
  </FollowUpModalPortal>;
}
function TemplateEditor({ template, close }) {
  const [params,setParams]=useState((template.body_parameters||[]).join(', '));
  const form=useForm({name:template.name,wait_days:template.wait_days,message:template.message,header_type:template.header_type||'none',image_url:template.image_url||'',meta_template_name:template.meta_template_name||'',meta_language:template.meta_language||'id',meta_status:template.meta_status||'draft',meta_category:template.meta_category||'marketing',body_parameters:template.body_parameters||[]});
  const submit=e=>{e.preventDefault();form.transform(data=>({...data,body_parameters:params.split(',').map(x=>x.trim()).filter(Boolean)})).put(`/follow-up-templates/${template.id}`,{preserveScroll:true,onSuccess:close});};
  return <FollowUpModalPortal close={close}>
    <div className="modal-backdrop followup-modal-backdrop-v91" role="presentation" onClick={close}>
      <form className="followup-composer followup-composer-v7 followup-modal-panel-v91" role="dialog" aria-modal="true" aria-label={`Edit template ${template.name}`} onClick={e=>e.stopPropagation()} onSubmit={submit}>
        <div className="modal-head"><div><span className="eyebrow">Template + Meta</span><h3>{template.name}</h3></div><button type="button" className="icon-button" onClick={close} aria-label="Tutup"><Icon name="close"/></button></div>
        <div className="form-grid two"><label className="field"><span>Nama</span><input value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/></label><label className="field"><span>Jeda hari</span><input type="number" value={form.data.wait_days} onChange={e=>form.setData('wait_days',Number(e.target.value))}/></label><label className="field"><span>Header</span><select value={form.data.header_type} onChange={e=>form.setData('header_type',e.target.value)}><option value="none">None</option><option value="image">Image</option></select></label><label className="field"><span>Image URL</span><input value={form.data.image_url} onChange={e=>form.setData('image_url',e.target.value)}/></label><label className="field"><span>Meta template name</span><input value={form.data.meta_template_name} onChange={e=>form.setData('meta_template_name',e.target.value)}/></label><label className="field"><span>Meta status</span><select value={form.data.meta_status} onChange={e=>form.setData('meta_status',e.target.value)}><option value="draft">Draft</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label></div>
        <label className="field"><span>Body parameters (comma separated)</span><input value={params} onChange={e=>setParams(e.target.value)}/></label>
        <label className="field"><span>Pesan draft/manual</span><textarea rows="8" value={form.data.message} onChange={e=>form.setData('message',e.target.value)}/></label>
        <button className="btn btn-primary">Simpan Template</button>
      </form>
    </div>
  </FollowUpModalPortal>;
}
export default function FollowUpsIndex({ queues, stats, templates, noReplyDays, leadAgeDays, whatsappApiReady, salesUsers=[], filters={} }) {
  const { auth }=usePage().props;
  const [tab,setTab]=useState(stats.response_needed?'response_needed':stats.lead_age_due?'lead_age_due':stats.no_reply?'no_reply':'scheduled');
  const [composer,setComposer]=useState(null);
  const [editing,setEditing]=useState(null);
  const items=queues[tab]||[];
  const selectedSales=useMemo(()=>salesUsers.find(u=>String(u.id)===String(filters.assigned_to||'')),[salesUsers,filters.assigned_to]);
  const changeSales=(e)=>{const id=e.target.value;router.get('/follow-ups',id?{assigned_to:id}:{},{replace:true,preserveState:false,preserveScroll:false});};

  return <AppLayout title="Follow Up" subtitle={auth.user.is_admin?'Monitor queue seluruh Account Executive dan drill-down per sales.':'Prioritaskan queue, review pesan, lalu catat hasil follow-up.'}>
    <Head title="Follow Up"/>
    <div className="followup-v7-page">
      {auth.user.is_admin&&<section className="panel followup-v7-admin-filter"><div><span className="eyebrow">Admin monitoring</span><h3>{selectedSales?`Queue ${selectedSales.name}`:'Semua Account Executive'}</h3><p>Filter queue untuk melihat database mana yang belum ditindaklanjuti oleh masing-masing sales.</p></div><div className="followup-v7-admin-actions"><select value={filters.assigned_to||''} onChange={changeSales}><option value="">Semua Account Executive</option>{salesUsers.map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</select>{selectedSales&&<Link href={`/prospects?assigned_to=${selectedSales.id}`} className="btn btn-secondary">Lihat Database</Link>}</div></section>}

      <section className="followup-summary-grid followup-v7-summary">{Object.keys(tabs).map(k=><article className={`followup-summary-card followup-v7-summary-card ${stats[k]?'has-work':''}`} key={k}><small>{tabs[k]}{k==='lead_age_due'?` · H-${leadAgeDays}`:''}</small><strong>{stats[k]||0}</strong><p>{k==='no_reply'?`${noReplyDays}+ hari tanpa respons`:helper[k]}</p></article>)}</section>

      <section className="panel followup-v7-work-panel"><div className="followup-tabs followup-v7-tabs">{Object.keys(tabs).map(k=><button type="button" className={tab===k?'active':''} onClick={()=>setTab(k)} key={k}><span>{tabs[k]}</span><b>{stats[k]||0}</b></button>)}</div><div className="followup-v7-queue-head"><div><span className="eyebrow">Work queue</span><h3>{tabs[tab]}</h3><p>{helper[tab]}</p></div><strong>{items.length} item</strong></div>{items.length?<div className="followup-v7-list">{items.map(p=><article className="followup-v7-card" key={p.id}><div className="followup-v7-card-head"><div className="followup-v7-company"><Link href={`/prospects/${p.id}`}><strong>{p.company_name}</strong></Link><small>{p.contact_name||'PIC belum ada'} · {p.phone||'WA belum ada'}</small></div><PriorityBadge priority={p.priority}/></div>{auth.user.is_admin&&<div className="followup-v7-owner">AE <b>{p.assigned_user?.name||'Belum diassign'}</b></div>}<div className="followup-v7-card-metrics"><span><small>Usia lead</small><strong>{p.lead_age_days||0} hari</strong></span><span><small>Follow-up</small><strong>{p.follow_up_count||0}x</strong></span><span><small>Status</small><strong>{p.status_label||p.status}</strong></span></div><p className="followup-v7-message">{p.suggested_message||'Pilih template saat review.'}</p><div className="followup-v7-card-footer"><small>{p.next_follow_up_at?'Reminder sudah dijadwalkan':'Belum ada reminder berikutnya'}</small><button type="button" className="btn btn-primary" onClick={()=>setComposer({item:p,type:tab})}><Icon name="whatsapp" size={16}/>Review & Follow-up</button></div></article>)}</div>:<EmptyState icon="check" title="Queue bersih" description="Tidak ada tindakan pada kategori ini."/>}</section>

      <section className="panel followup-v7-template-panel"><div className="panel-head"><div><span className="eyebrow">Template library</span><h3>WhatsApp template</h3></div></div><div className="template-card-grid followup-v7-template-grid">{templates.map(t=><article className="template-card followup-v7-template-card" key={t.id}>{t.image_url&&<img className="sv-template-thumb" src={t.image_url} alt=""/>}<div><strong>{t.name}</strong><small>{t.meta_template_name||'Manual draft'} · {t.meta_status}</small><p>{t.message}</p></div>{auth.user.is_admin&&<button type="button" className="btn btn-secondary" onClick={()=>setEditing(t)}>Edit Template</button>}</article>)}</div></section>
    </div>
    {composer&&<Composer item={composer.item} queueType={composer.type} templates={templates} apiReady={whatsappApiReady} close={()=>setComposer(null)}/>} {editing&&<TemplateEditor template={editing} close={()=>setEditing(null)}/>}
  </AppLayout>;
}
