import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar, PriorityBadge, ScoreDots, StatusBadge } from '../../Components/Ui';
import { date, dateTime, isDue, money } from '../../Utils/format';

export default function Show({ prospect:p, statuses, activityTypes }) {
    const { auth }=usePage().props;
    const activity=useForm({type:'note',title:'',description:'',occurred_at:'',next_follow_up_at:'',status:''});
    const submitActivity=(e)=>{e.preventDefault(); activity.post(`/prospects/${p.id}/activities`,{preserveScroll:true,onSuccess:()=>activity.reset('title','description','occurred_at')});};
    const changeStatus=(status)=>router.patch(`/prospects/${p.id}/status`,{status},{preserveScroll:true});
    const remove=()=>{if(confirm(`Hapus ${p.company_name}? Data aktivitas juga akan terhapus.`)) router.delete(`/prospects/${p.id}`);};
    const digits=(p.phone||'').replace(/\D/g,'');

    return <AppLayout title={p.company_name} subtitle={[p.city,p.service,p.route].filter(Boolean).join(' · ')||'Profil prospek'} action={<div className="action-group"><Link href="/prospects" className="btn btn-secondary"><Icon name="arrowLeft" size={17}/><span className="hide-mobile">Kembali</span></Link><Link href={`/prospects/${p.id}/edit`} className="btn btn-primary"><Icon name="edit" size={17}/>Edit</Link></div>}>
        <Head title={p.company_name}/>
        <section className="prospect-hero panel">
            <div className="prospect-hero-main"><span className="hero-company-avatar">{p.company_name?.[0]}</span><div><div className="hero-badges"><PriorityBadge priority={p.priority}/><StatusBadge status={p.status} label={p.status_label}/></div><h2>{p.company_name}</h2><p>{p.pain_hypothesis||'Belum ada dugaan masalah. Lakukan riset atau discovery untuk melengkapi pain hypothesis.'}</p></div></div>
            <div className="hero-score"><strong>{p.total_score}<small>/9</small></strong><ScoreDots value={p.total_score>=7?3:p.total_score>=4?2:1}/><span>Lead score</span></div>
        </section>

        <div className="detail-layout">
            <div className="detail-main">
                <section className="panel"><div className="panel-head"><div><span className="eyebrow">Company intelligence</span><h3>Informasi utama</h3></div></div><div className="detail-info-grid">
                    <div><span>Kota</span><strong>{p.city||'—'}</strong></div><div><span>Layanan</span><strong>{p.service||'—'}</strong></div><div><span>Rute / market</span><strong>{p.route||'—'}</strong></div><div><span>Ukuran</span><strong>{p.company_size||'—'}</strong></div>
                    <div><span>Sistem saat ini</span><strong>{p.current_system||'Belum diketahui'}</strong></div><div><span>Tracking portal</span><strong>{p.tracking_portal===true?'Ada':p.tracking_portal===false?'Tidak ada':'Belum diketahui'}</strong></div><div><span>Potensi deal</span><strong>{money(p.estimated_deal_value)}</strong></div><div><span>Account Executive</span><strong>{p.assigned_user?.name||'Belum diassign'}</strong></div>
                </div></section>

                <section className="panel"><div className="panel-head"><div><span className="eyebrow">Activity timeline</span><h3>Riwayat interaksi</h3></div><span className="count-bubble">{p.activities?.length||0}</span></div>
                    <form className="activity-form" onSubmit={submitActivity}><div className="activity-row"><select value={activity.data.type} onChange={e=>activity.setData('type',e.target.value)}>{Object.entries(activityTypes).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select><input value={activity.data.title} onChange={e=>activity.setData('title',e.target.value)} placeholder="Judul aktivitas *"/><select value={activity.data.status} onChange={e=>activity.setData('status',e.target.value)}><option value="">Status tetap</option>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></div><textarea rows="3" value={activity.data.description} onChange={e=>activity.setData('description',e.target.value)} placeholder="Ringkasan percakapan, insight, keberatan client..."/><div className="activity-row"><label><span>Follow-up berikutnya</span><input type="datetime-local" value={activity.data.next_follow_up_at} onChange={e=>activity.setData('next_follow_up_at',e.target.value)}/></label><button className="btn btn-dark" disabled={activity.processing}><Icon name="plus" size={16}/>Catat Aktivitas</button></div>{Object.values(activity.errors).length>0&&<small className="field-error">{Object.values(activity.errors)[0]}</small>}</form>
                    <div className="timeline">{p.activities?.length?p.activities.map((a)=><article className="timeline-item" key={a.id}><span className={`timeline-icon activity-${a.type}`}><Icon name={a.type==='meeting'?'users':a.type==='email'?'mail':a.type==='call'?'phone':a.type==='customer_reply'?'whatsapp':a.type==='status_change'?'pipeline':'briefcase'} size={16}/></span><div><div className="timeline-top"><strong>{a.title}</strong><time>{dateTime(a.occurred_at)}</time></div>{a.description&&<p>{a.description}</p>}<small>{a.type_label} · {a.user?.name||'System'}</small></div></article>):<div className="mini-empty">Belum ada aktivitas selain pembuatan prospek.</div>}</div>
                </section>
            </div>

            <aside className="detail-side">
                <section className="panel sticky-panel"><span className="eyebrow">Next action</span><h3 className="side-title">Hubungi & lanjutkan</h3>
                    <div className="contact-card"><Avatar name={p.contact_name||p.company_name}/><div><strong>{p.contact_name||'PIC belum ditemukan'}</strong><span>{p.contact_position||'Cari decision maker yang tepat'}</span></div></div>
                    <div className="quick-actions">{p.phone&&<a href={`tel:${p.phone}`} className="quick-action"><Icon name="phone" size={18}/><span>Telepon</span></a>}{digits&&<a href={`https://wa.me/${digits}`} target="_blank" rel="noreferrer" className="quick-action"><Icon name="whatsapp" size={18}/><span>WhatsApp</span></a>}{p.email&&<a href={`mailto:${p.email}`} className="quick-action"><Icon name="mail" size={18}/><span>Email</span></a>}{p.website&&<a href={p.website} target="_blank" rel="noreferrer" className="quick-action"><Icon name="external" size={18}/><span>Website</span></a>}</div>
                    <div className={`followup-box ${isDue(p.next_follow_up_at)?'overdue':''}`}><Icon name="calendar" size={19}/><div><span>Follow-up berikutnya</span><strong>{p.next_follow_up_at?dateTime(p.next_follow_up_at):'Belum dijadwalkan'}</strong></div></div>
                    <label className="field compact-field"><span>Update pipeline</span><select value={p.status} onChange={e=>changeStatus(e.target.value)}>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></label>
                    <div className="source-box"><span>Sumber data</span><strong>{p.source_name||'Manual'}</strong>{p.source_url&&<a href={p.source_url} target="_blank" rel="noreferrer">Buka sumber <Icon name="external" size={14}/></a>}</div>
                    {p.notes&&<div className="notes-box"><span>Catatan internal</span><p>{p.notes}</p></div>}
                    {auth.user.is_admin&&<button className="btn btn-danger-soft btn-block" onClick={remove}><Icon name="trash" size={16}/>Hapus Prospek</button>}
                </section>
            </aside>
        </div>
    </AppLayout>;
}
