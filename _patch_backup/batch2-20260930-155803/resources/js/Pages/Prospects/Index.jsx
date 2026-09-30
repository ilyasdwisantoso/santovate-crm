import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar, EmptyState, PriorityBadge, ScoreDots, StatusBadge } from '../../Components/Ui';
import { dateTime, isDue, money } from '../../Utils/format';

function Pagination({ links=[] }) {
    if (links.length <= 3) return null;
    return <div className="pagination">{links.map((link,i)=>link.url?<Link key={i} href={link.url} preserveScroll className={link.active?'active':''} dangerouslySetInnerHTML={{__html:link.label}}/>:<span key={i} className="disabled" dangerouslySetInnerHTML={{__html:link.label}}/>)}</div>;
}

export default function Index({ prospects, filters, statuses, priorities, salesUsers }) {
    const { auth }=usePage().props;
    const [form,setForm]=useState({q:filters.q||'',priority:filters.priority||'',status:filters.status||'',assigned_to:filters.assigned_to||'',followup:filters.followup||'',import_batch:filters.import_batch||''});
    const apply=(e)=>{e?.preventDefault(); const params=Object.fromEntries(Object.entries(form).filter(([,v])=>v!=='')); router.get('/prospects',params,{preserveState:true,replace:true});};
    const reset=()=>{setForm({q:'',priority:'',status:'',assigned_to:'',followup:'',import_batch:''}); router.get('/prospects');};
    const actions=<div className="action-group">{auth.user.is_admin&&<Link href="/imports" className="btn btn-secondary hide-mobile"><Icon name="upload" size={17}/>Import Data</Link>}<Link href="/prospects/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah Prospek</Link></div>;

    return <AppLayout title="Prospek" subtitle="Database perusahaan, prioritas, PIC, dan tindakan berikutnya dalam satu tempat." action={actions}>
        <Head title="Prospek"/>
        {filters.import_batch&&<div className="import-filter-banner"><span className="import-filter-icon"><Icon name="upload" size={18}/></span><div><strong>Menampilkan prospek dari Import #{filters.import_batch}</strong><small>Filter ini dibuka dari notifikasi atau riwayat import.</small></div><button className="btn btn-ghost btn-sm" onClick={()=>{setForm({...form,import_batch:''});router.get('/prospects')}}>Lihat semua prospek</button></div>}
        <section className="panel no-pad">
            <form className="filters" onSubmit={apply}>
                <div className="search-box"><Icon name="search" size={18}/><input value={form.q} onChange={(e)=>setForm({...form,q:e.target.value})} placeholder="Cari perusahaan, kota, PIC, layanan..."/></div>
                <select value={form.priority} onChange={(e)=>setForm({...form,priority:e.target.value})}><option value="">Semua prioritas</option>{Object.entries(priorities).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select>
                <select value={form.status} onChange={(e)=>setForm({...form,status:e.target.value})}><option value="">Semua status</option>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select>
                {auth.user.is_admin&&<select value={form.assigned_to} onChange={(e)=>setForm({...form,assigned_to:e.target.value})}><option value="">Semua Account Executive</option><option value="unassigned">Belum ditugaskan</option>{salesUsers.map((u)=><option key={u.id} value={u.id}>{u.name}</option>)}</select>}
                <label className="filter-check"><input type="checkbox" checked={form.followup==='due'} onChange={(e)=>setForm({...form,followup:e.target.checked?'due':''})}/><span>Follow-up jatuh tempo</span></label>
                <button className="btn btn-dark" type="submit"><Icon name="filter" size={16}/>Terapkan</button>
                {Object.values(filters).some(Boolean)&&<button type="button" className="btn btn-ghost" onClick={reset}>Reset</button>}
            </form>

            {prospects.data.length ? <>
                <div className="prospect-table-wrap desktop-only"><table className="prospect-table"><thead><tr><th>Perusahaan</th><th>Kontak / PIC</th><th>Prioritas</th><th>Status</th><th>Account Executive</th><th>Follow-up</th><th>Potensi</th><th></th></tr></thead><tbody>{prospects.data.map((p)=><tr key={p.id}><td><Link href={`/prospects/${p.id}`} className="company-cell"><span className="company-avatar">{p.company_name?.[0]}</span><div><strong>{p.company_name}</strong><small>{[p.city,p.service].filter(Boolean).join(' · ')||'Data belum lengkap'}</small></div></Link></td><td><div className="stacked-cell"><strong>{p.contact_name||'Belum ada PIC'}</strong><small>{p.contact_position||p.email||p.phone||'Kontak belum ditemukan'}</small></div></td><td><div className="priority-cell"><PriorityBadge priority={p.priority}/><ScoreDots value={p.total_score>=7?3:p.total_score>=4?2:1}/><small>{p.total_score}/9</small></div></td><td><StatusBadge status={p.status} label={p.status_label}/></td><td>{p.assigned_user?<div className="user-cell"><Avatar name={p.assigned_user.name} size="sm"/><span>{p.assigned_user.name}</span></div>:<span className="muted">Belum diassign</span>}</td><td><div className={`stacked-cell ${isDue(p.next_follow_up_at)?'due':''}`}><strong>{p.next_follow_up_at?dateTime(p.next_follow_up_at):'Belum dijadwalkan'}</strong><small>{isDue(p.next_follow_up_at)?'Perlu tindakan':'Terjadwal'}</small></div></td><td><strong>{money(p.estimated_deal_value)}</strong></td><td><Link className="icon-button" href={`/prospects/${p.id}`}><Icon name="chevron" size={17}/></Link></td></tr>)}</tbody></table></div>

                <div className="mobile-prospect-list mobile-only">{prospects.data.map((p)=><Link href={`/prospects/${p.id}`} className="mobile-prospect-card" key={p.id}><div className="mobile-prospect-top"><div className="company-cell"><span className="company-avatar">{p.company_name?.[0]}</span><div><strong>{p.company_name}</strong><small>{[p.city,p.service].filter(Boolean).join(' · ')||'Data belum lengkap'}</small></div></div><span className="score-pill">{p.total_score}/9</span></div><div className="mobile-badges"><PriorityBadge priority={p.priority}/><StatusBadge status={p.status} label={p.status_label}/></div><div className="mobile-prospect-meta"><div><span>PIC</span><strong>{p.contact_name||'Belum ada'}</strong></div><div><span>Follow-up</span><strong className={isDue(p.next_follow_up_at)?'text-danger':''}>{p.next_follow_up_at?dateTime(p.next_follow_up_at):'Belum ada'}</strong></div><div><span>Potensi</span><strong>{money(p.estimated_deal_value)}</strong></div></div></Link>)}</div>
                <div className="table-footer"><span>Menampilkan {prospects.from}–{prospects.to} dari {prospects.total} prospek</span><Pagination links={prospects.links}/></div>
            </>:<EmptyState icon="building" title="Belum ada prospek" description="Tambahkan prospek manual atau gunakan fitur Import Data untuk memasukkan database perusahaan." action={<div className="empty-actions">{auth.user.is_admin&&<Link href="/imports" className="btn btn-secondary"><Icon name="upload" size={17}/>Import Data</Link>}<Link href="/prospects/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah Prospek</Link></div>}/>} 
        </section>
    </AppLayout>;
}
