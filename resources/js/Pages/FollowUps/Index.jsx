import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar, EmptyState, PriorityBadge, StatusBadge } from '../../Components/Ui';
import { dateTime } from '../../Utils/format';

const normalizeWhatsApp = (value = '') => {
    let digits = String(value).replace(/\D/g, '');
    if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
    if (digits.startsWith('8')) digits = `62${digits}`;
    return digits;
};

const daysSince = (value) => {
    if (!value) return 0;
    return Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 86400000));
};

function ComposerModal({ prospect, queueType, onClose }) {
    const isReply = queueType === 'response_needed';
    const form = useForm({
        message: prospect.suggested_message || '',
        next_follow_up_days: 5,
    });
    const [opened, setOpened] = useState(false);
    const waNumber = normalizeWhatsApp(prospect.phone);

    const openWhatsApp = () => {
        if (!waNumber || !form.data.message.trim()) return;
        const url = `https://wa.me/${waNumber}?text=${encodeURIComponent(form.data.message.trim())}`;
        window.open(url, '_blank', 'noopener,noreferrer');
        setOpened(true);
    };

    const markSent = () => {
        form.post(`/follow-ups/${prospect.id}/sent`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return <div className="modal-backdrop" onClick={onClose}>
        <section className="followup-composer" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
                <div>
                    <span className="eyebrow">{isReply ? 'Customer sudah membalas' : 'Review sebelum kirim'}</span>
                    <h3>{isReply ? 'Siapkan balasan WhatsApp' : 'Siapkan follow-up WhatsApp'}</h3>
                    <p>{prospect.company_name} · {prospect.contact_name || 'PIC belum diketahui'}</p>
                </div>
                <button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20}/></button>
            </div>

            <div className="composer-recipient">
                <Avatar name={prospect.contact_name || prospect.company_name}/>
                <div><span>Dikirim ke</span><strong>{prospect.phone || 'Nomor WhatsApp belum tersedia'}</strong></div>
                <StatusBadge status={prospect.status} label={prospect.status_label}/>
            </div>

            <label className="field">
                <span>Pesan</span>
                <textarea rows="10" value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} />
                <small className="field-hint">Template hanya draft. Account Executive bebas mengedit sebelum membuka WhatsApp.</small>
                {form.errors.message && <small className="field-error">{form.errors.message}</small>}
            </label>

            <label className="field compact-field">
                <span>Jika belum ada respons lagi, ingatkan setelah</span>
                <select value={form.data.next_follow_up_days} onChange={(e) => form.setData('next_follow_up_days', Number(e.target.value))}>
                    <option value={1}>1 hari</option>
                    <option value={3}>3 hari</option>
                    <option value={5}>5 hari</option>
                    <option value={7}>7 hari</option>
                    <option value={14}>14 hari</option>
                    <option value={0}>Tidak dijadwalkan</option>
                </select>
            </label>

            {!waNumber && <div className="alert alert-error">Nomor WhatsApp belum tersedia. Lengkapi nomor prospect sebelum mengirim pesan.</div>}
            {opened && <div className="composer-confirmation"><Icon name="check" size={18}/><div><strong>WhatsApp sudah dibuka</strong><small>Setelah Anda benar-benar menekan Send di WhatsApp, kembali ke CRM dan tandai sebagai sudah dikirim.</small></div></div>}

            <div className="modal-actions composer-actions">
                <button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button>
                <button type="button" className="btn btn-whatsapp" disabled={!waNumber || !form.data.message.trim()} onClick={openWhatsApp}><Icon name="whatsapp" size={17}/>Buka WhatsApp</button>
                <button type="button" className="btn btn-primary" disabled={!opened || form.processing} onClick={markSent}><Icon name="check" size={17}/>{form.processing ? 'Menyimpan...' : 'Tandai Sudah Dikirim'}</button>
            </div>
        </section>
    </div>;
}

function ReplyModal({ prospect, onClose }) {
    const form = useForm({ note: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(`/follow-ups/${prospect.id}/customer-reply`, { preserveScroll: true, onSuccess: onClose });
    };
    return <div className="modal-backdrop" onClick={onClose}>
        <form className="modal-card followup-reply-modal" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
            <div className="modal-head"><div><span className="eyebrow">Update feedback</span><h3>Catat balasan customer</h3><p>{prospect.company_name}</p></div><button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20}/></button></div>
            <label className="field"><span>Ringkasan balasan customer</span><textarea rows="6" value={form.data.note} onChange={(e)=>form.setData('note',e.target.value)} placeholder="Contoh: Customer tertarik dan meminta demo minggu depan..."/></label>
            <div className="modal-actions"><button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button><button className="btn btn-primary" disabled={form.processing}>Simpan & Masukkan ke Perlu Balas</button></div>
        </form>
    </div>;
}

function TemplateEditor({ template, onClose }) {
    const form = useForm({ name: template.name, wait_days: template.wait_days, message: template.message });
    const submit = (e) => { e.preventDefault(); form.put(`/follow-up-templates/${template.id}`, { preserveScroll:true, onSuccess:onClose }); };
    return <div className="modal-backdrop" onClick={onClose}><form className="followup-composer" onClick={(e)=>e.stopPropagation()} onSubmit={submit}>
        <div className="modal-head"><div><span className="eyebrow">Template CRM</span><h3>{template.name}</h3><p>Gunakan placeholder seperti {'{contact_name}'}, {'{company_name}'}, dan {'{ae_name}'}.</p></div><button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20}/></button></div>
        <div className="form-grid single-mobile"><label className="field"><span>Nama template</span><input value={form.data.name} onChange={(e)=>form.setData('name',e.target.value)}/></label><label className="field"><span>Jeda otomatis</span><input type="number" min="0" max="60" value={form.data.wait_days} onChange={(e)=>form.setData('wait_days',Number(e.target.value))}/><small className="field-hint">Untuk template belum ada feedback, default 5 hari.</small></label></div>
        <label className="field"><span>Isi template</span><textarea rows="10" value={form.data.message} onChange={(e)=>form.setData('message',e.target.value)}/></label>
        {Object.values(form.errors).length>0 && <div className="alert alert-error">{Object.values(form.errors)[0]}</div>}
        <div className="modal-actions"><button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button><button className="btn btn-primary" disabled={form.processing}>Simpan Template</button></div>
    </form></div>;
}

function QueueRow({ prospect, type, onCompose, onReply }) {
    const isResponse = type === 'response_needed';
    const reference = isResponse ? prospect.last_customer_reply_at : (type === 'scheduled' ? prospect.next_follow_up_at : prospect.last_outbound_at);
    const helper = isResponse
        ? `Customer membalas ${dateTime(prospect.last_customer_reply_at)}`
        : type === 'scheduled'
            ? `Jadwal ${dateTime(prospect.next_follow_up_at)}`
            : `${daysSince(prospect.last_outbound_at)} hari tanpa feedback`;
    const snoozeTomorrow = () => router.post(`/follow-ups/${prospect.id}/snooze`, { until: new Date(Date.now()+86400000).toISOString() }, { preserveScroll:true });

    return <tr>
        <td><Link href={`/prospects/${prospect.id}`} className="company-cell"><span className="company-avatar">{prospect.company_name?.[0]}</span><div><strong>{prospect.company_name}</strong><small>{[prospect.city, prospect.service].filter(Boolean).join(' · ') || 'Data belum lengkap'}</small></div></Link></td>
        <td><div className="stacked-cell"><strong>{prospect.contact_name || 'PIC belum ada'}</strong><small>{prospect.phone || 'Nomor WA belum ada'}</small></div></td>
        <td><PriorityBadge priority={prospect.priority}/></td>
        <td><div className={`followup-age ${isResponse?'urgent':''}`}><strong>{isResponse ? 'Perlu dibalas' : type==='scheduled' ? 'Jatuh tempo' : `${daysSince(reference)} hari`}</strong><small>{helper}</small></div></td>
        <td>{prospect.assigned_user ? <div className="user-cell"><Avatar name={prospect.assigned_user.name} size="sm"/><span>{prospect.assigned_user.name}</span></div> : <span className="muted">Belum ada AE</span>}</td>
        <td><div className="row-actions"><button className="btn btn-sm btn-primary" onClick={()=>onCompose(prospect,type)}><Icon name="whatsapp" size={15}/>{isResponse?'Siapkan Balasan':'Siapkan Follow-up'}</button>{!isResponse&&<button className="btn btn-sm btn-secondary" onClick={()=>onReply(prospect)}>Customer Membalas</button>}<button className="icon-button" title="Ingatkan besok" onClick={snoozeTomorrow}><Icon name="calendar" size={16}/></button></div></td>
    </tr>;
}

function MobileQueueCard({ prospect, type, onCompose, onReply }) {
    const isResponse = type === 'response_needed';
    const snoozeTomorrow = () => router.post(`/follow-ups/${prospect.id}/snooze`, { until: new Date(Date.now()+86400000).toISOString() }, { preserveScroll:true });
    return <article className={`followup-mobile-card ${isResponse?'urgent':''}`}>
        <div className="followup-mobile-head"><div className="company-cell"><span className="company-avatar">{prospect.company_name?.[0]}</span><div><strong>{prospect.company_name}</strong><small>{prospect.contact_name || 'PIC belum diketahui'}</small></div></div><PriorityBadge priority={prospect.priority}/></div>
        <div className="followup-mobile-info"><span><small>Status tugas</small><strong>{isResponse?'Perlu dibalas':type==='scheduled'?'Follow-up terjadwal':`${daysSince(prospect.last_outbound_at)} hari tanpa feedback`}</strong></span><span><small>WhatsApp</small><strong>{prospect.phone || 'Belum ada nomor'}</strong></span><span><small>Account Executive</small><strong>{prospect.assigned_user?.name || 'Belum ditugaskan'}</strong></span></div>
        <div className="followup-mobile-actions"><button className="btn btn-primary" onClick={()=>onCompose(prospect,type)}><Icon name="whatsapp" size={16}/>{isResponse?'Balas':'Follow-up'}</button>{!isResponse&&<button className="btn btn-secondary" onClick={()=>onReply(prospect)}>Ada Balasan</button>}<button className="icon-button" onClick={snoozeTomorrow}><Icon name="calendar" size={17}/></button></div>
    </article>;
}

export default function FollowUpsIndex({ queues, stats, templates, noReplyDays }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState(stats.response_needed > 0 ? 'response_needed' : 'no_reply');
    const [composer, setComposer] = useState(null);
    const [replyProspect, setReplyProspect] = useState(null);
    const [editingTemplate, setEditingTemplate] = useState(null);

    const tabs = useMemo(() => [
        { key:'response_needed', label:'Perlu Balas', count:stats.response_needed, description:'Customer sudah merespons tetapi Account Executive belum membalas lagi.' },
        { key:'no_reply', label:'Belum Ada Feedback', count:stats.no_reply, description:`Sudah ${noReplyDays}+ hari sejak kontak terakhir dan belum ada balasan customer.` },
        { key:'scheduled', label:'Terjadwal', count:stats.scheduled, description:'Follow-up yang dijadwalkan dan sudah jatuh tempo hari ini.' },
    ], [stats, noReplyDays]);
    const current = queues[tab] || [];
    const currentTab = tabs.find((item)=>item.key===tab);

    return <AppLayout title="Follow Up" subtitle="Work queue harian CRM Account Executive — review pesan, edit bila perlu, lalu kirim melalui WhatsApp.">
        <Head title="Follow Up Account Executive"/>

        <section className="followup-summary-grid">
            <article className="followup-summary-card danger"><span className="summary-icon"><Icon name="whatsapp" size={19}/></span><div><small>Perlu Balas</small><strong>{stats.response_needed}</strong><p>Customer sudah memberi feedback</p></div></article>
            <article className="followup-summary-card warning"><span className="summary-icon"><Icon name="alert" size={19}/></span><div><small>Belum Ada Feedback</small><strong>{stats.no_reply}</strong><p>{noReplyDays}+ hari tanpa respons</p></div></article>
            <article className="followup-summary-card info"><span className="summary-icon"><Icon name="calendar" size={19}/></span><div><small>Terjadwal</small><strong>{stats.scheduled}</strong><p>Jatuh tempo hari ini</p></div></article>
            <article className="followup-summary-card"><span className="summary-icon"><Icon name="target" size={19}/></span><div><small>Total Pekerjaan</small><strong>{stats.total}</strong><p>Queue yang membutuhkan perhatian</p></div></article>
        </section>

        <section className="panel followup-work-panel">
            <div className="followup-tabs">{tabs.map((item)=><button key={item.key} className={tab===item.key?'active':''} onClick={()=>setTab(item.key)}><span>{item.label}</span><b>{item.count}</b></button>)}</div>
            <div className="followup-tab-copy"><div><span className="eyebrow">Daily work queue</span><h3>{currentTab?.label}</h3><p>{currentTab?.description}</p></div><Link href="/prospects" className="btn btn-secondary"><Icon name="building" size={16}/>Semua Prospek</Link></div>

            {current.length ? <>
                <div className="prospect-table-wrap desktop-only"><table className="prospect-table followup-table"><thead><tr><th>Perusahaan</th><th>PIC / WhatsApp</th><th>Prioritas</th><th>Kondisi</th><th>Account Executive</th><th>Tindakan</th></tr></thead><tbody>{current.map((prospect)=><QueueRow key={prospect.id} prospect={prospect} type={tab} onCompose={(p,t)=>setComposer({prospect:p,type:t})} onReply={setReplyProspect}/>)}</tbody></table></div>
                <div className="mobile-only followup-mobile-list">{current.map((prospect)=><MobileQueueCard key={prospect.id} prospect={prospect} type={tab} onCompose={(p,t)=>setComposer({prospect:p,type:t})} onReply={setReplyProspect}/>)}</div>
            </> : <EmptyState icon="check" title="Queue ini sudah bersih" description="Tidak ada prospect yang membutuhkan tindakan pada kategori ini."/>}
        </section>

        {auth.user.is_admin && <section className="panel followup-template-panel">
            <div className="panel-head"><div><span className="eyebrow">Admin settings</span><h3>Template follow-up CRM</h3><p>Template hanya menjadi draft awal; Account Executive tetap wajib review sebelum mengirim.</p></div></div>
            <div className="template-card-grid">{templates.map((template)=><article className="template-card" key={template.id}><div><span className="template-type">{template.trigger_type==='no_reply'?`${template.wait_days} hari tanpa feedback`:'Customer sudah membalas'}</span><h4>{template.name}</h4><p>{template.message}</p></div><button className="btn btn-secondary btn-sm" onClick={()=>setEditingTemplate(template)}><Icon name="edit" size={15}/>Edit Template</button></article>)}</div>
            <div className="template-variables"><strong>Placeholder:</strong><code>{'{contact_name}'}</code><code>{'{company_name}'}</code><code>{'{ae_name}'}</code><code>{'{ae_first_name}'}</code><code>{'{service}'}</code><code>{'{portfolio_url}'}</code></div>
        </section>}

        {composer && <ComposerModal prospect={composer.prospect} queueType={composer.type} onClose={()=>setComposer(null)}/>} 
        {replyProspect && <ReplyModal prospect={replyProspect} onClose={()=>setReplyProspect(null)}/>} 
        {editingTemplate && <TemplateEditor template={editingTemplate} onClose={()=>setEditingTemplate(null)}/>} 
    </AppLayout>;
}
