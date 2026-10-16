import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { dateTime } from '../../Utils/format';

function LeadCard({ lead, salesUsers }) {
    const { crm } = usePage().props;
    const [individualName,setIndividualName] = useState(lead.profile_name || '');
    const [forceDuplicate,setForceDuplicate] = useState(false);
    const [status, setStatus] = useState(lead.status);
    const [kind, setKind] = useState(lead.lead_kind);
    const [assignee, setAssignee] = useState(lead.assigned_to ?? '');
    const [notes, setNotes] = useState(lead.notes || '');
    const [companyName, setCompanyName] = useState('');
    const [city, setCity] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    useEffect(()=>{setForceDuplicate(false);},[kind,companyName,individualName,city]);

    const submit = (url, payload) => {
        setError('');
        router.post(url, payload, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errors) => setError(Object.values(errors || {})[0] || 'Gagal menyimpan perubahan.'),
        });
    };
    const path = `/whatsapp/leads/${lead.id}`;
    return <article className="wa951-card">
        <div className="wa951-card-head">
            <div>
                <div className="wa951-identification">
                    <strong>{lead.profile_name || 'Nomor belum bernama'}</strong>
                    <span className="wa951-status">{lead.prospect ? 'Terhubung' : lead.status === 'qualified' ? 'Terkualifikasi' : lead.status === 'ignored' ? 'Diabaikan' : 'Menunggu review'}</span>
                </div>
                <p>{lead.phone} · {lead.source_name}</p>
                <small>Terakhir masuk: {dateTime(lead.last_message_at)}</small>
            </div>
            {lead.prospect && <Link href={`/prospects/${lead.prospect.id}`} className="btn btn-soft btn-sm">Lihat prospek <Icon name="chevron" size={14}/></Link>}
        </div>
        {lead.last_message && <blockquote className="wa951-message">{lead.last_message}</blockquote>}
        {lead.source_id && <small className="wa951-referral">Meta Referral ID: {lead.source_id} · Penempatan iklan belum terverifikasi</small>}
        <div className="wa951-fields">
            <label><span>Jenis calon pelanggan</span><select value={kind} onChange={e => setKind(e.target.value)} disabled={processing || Boolean(lead.prospect)}>
                <option value="unknown">Belum diketahui</option><option value="individual">Individu / B2C</option><option value="business">Perusahaan / B2B</option>
            </select></label>
            <label><span>Status review</span><select value={status} onChange={e => setStatus(e.target.value)} disabled={processing}>
                <option value="pending">Perlu review</option><option value="qualified">Terkualifikasi</option><option value="ignored">Diabaikan</option>
            </select></label>
            <label><span>Account Executive</span><select value={assignee} onChange={e => setAssignee(e.target.value)} disabled={processing}>
                <option value="">Belum ditugaskan</option>{salesUsers.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
            </select></label>
            <label className="wa951-wide"><span>Catatan internal</span><textarea rows={2} value={notes} maxLength={2000} disabled={processing} onChange={e => setNotes(e.target.value)} placeholder="Kebutuhan dan rencana tindak lanjut pelanggan"/></label>
        </div>
        <div className="wa951-actions"><button className="btn btn-primary btn-sm" type="button" disabled={processing} onClick={() => submit(`${path}/review`, { status, lead_kind: kind, assigned_to: assignee || null, notes })}><Icon name="check" size={16}/> Simpan review</button></div>
        {!lead.prospect && <div className="wa951-convert">
            {lead.matching_prospects?.length > 0 && <div>
                <h4>Hubungkan prospek dengan nomor yang sama</h4>
                <div className="wa951-match-list">{lead.matching_prospects.map(p => <button key={p.id} type="button" className="btn btn-soft btn-sm" disabled={processing} onClick={() => submit(`${path}/link`, { prospect_id: p.id })}>{p.name} <Icon name="chevron" size={13}/></button>)}</div>
            </div>}
            {kind === 'business' ? <div>
                <h4>Konversi ke prospek perusahaan baru</h4>
                <p>Masukkan nama perusahaan sebenarnya. Periksa duplikasi dan kapasitas paket sebelum konversi.</p>
                <div className="wa951-convert-form"><label><span>Nama perusahaan *</span><input value={companyName} maxLength={255} onChange={e => setCompanyName(e.target.value)} placeholder="PT / CV / Nama usaha"/></label><label><span>Kota (opsional)</span><input value={city} maxLength={255} onChange={e => setCity(e.target.value)}/></label><button type="button" className="btn btn-dark btn-sm" disabled={processing || companyName.trim().length < 2} onClick={() => submit(`${path}/convert`, { entity_type: 'business', company_name: companyName, city, force_duplicate: forceDuplicate })}>Buat prospek B2B</button></div>
            </div> : kind === 'individual' && crm?.native_individuals_enabled ? <div>
                <h4>Konversi ke prospek individu baru</h4>
                <p>Konfirmasi nama pelanggan. Satu individu memakai satu kuota Prospect. Konversi tidak memberikan persetujuan marketing.</p>
                <div className="wa951-convert-form"><label><span>Nama individu *</span><input value={individualName} maxLength={255} onChange={e=>setIndividualName(e.target.value)} autoComplete="name"/></label><label><span>Kota (opsional)</span><input value={city} maxLength={255} onChange={e=>setCity(e.target.value)}/></label><button type="button" className="btn btn-dark btn-sm" disabled={processing || !individualName.trim()} onClick={()=>submit(`${path}/convert`,{entity_type:'individual',individual_name:individualName,city,force_duplicate:forceDuplicate})}>Buat prospek B2C</button></div>
            </div> : <p className="wa951-b2c-note">{kind === 'individual' ? 'Konversi B2C belum diaktifkan. Kontak tetap tersimpan dan dapat direview tanpa memakai kuota Prospect.' : 'Pilih jenis pelanggan terlebih dahulu. Nomor baru tetap tersimpan sebagai lead WhatsApp.'}</p>}
            {(kind==='business' || (kind==='individual' && crm?.native_individuals_enabled)) && <label className="check-line"><input type="checkbox" checked={forceDuplicate} onChange={e=>setForceDuplicate(e.target.checked)}/><span>Saya telah meninjau kandidat duplikat; identitas ini berbeda dan harus disimpan terpisah.</span></label>}
        </div>}
        {error && <div className="alert alert-error" role="alert">{error}</div>}
    </article>;
}

export default function Leads({ contacts, filters = {}, salesUsers = [] }) {
    const [search, setSearch] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || 'pending');
    const searchNow = (event) => { event.preventDefault(); router.get('/whatsapp/leads', { q: search.trim(), status }, { preserveState: false, replace: true }); };
    const selectStatus = (value) => { setStatus(value); router.get('/whatsapp/leads', { q: search.trim(), status: value }, { replace: true }); };
    return <AppLayout title="WhatsApp Leads" subtitle="Review kontak masuk, atribusi dan assignment. Pesan tetap aman meskipun belum menjadi Prospek.">
        <Head title="WhatsApp Leads"/>
        <section className="panel wa951-intro"><div><span className="eyebrow">Lead Capture · WA-01B</span><h3>Antrean kontak WhatsApp</h3><p>Data berasal dari webhook Meta yang terverifikasi. Fitur ini belum mengaktifkan koneksi WhatsApp Business HP atau pengiriman balasan otomatis.</p></div><Link href="/settings/whatsapp" className="btn btn-secondary btn-sm">Pengaturan Channel</Link></section>
        <section className="panel wa951-toolbar"><form onSubmit={searchNow} role="search" aria-label="Cari kontak WhatsApp"><label><span>Cari nama atau nomor</span><input value={search} maxLength={80} onChange={e => setSearch(e.target.value)} placeholder="Nama profil atau 628..."/></label><button className="btn btn-dark" type="submit"><Icon name="search" size={16}/>Cari</button></form><label><span>Filter status</span><select value={status} onChange={e => selectStatus(e.target.value)}><option value="pending">Perlu review</option><option value="qualified">Terkualifikasi</option><option value="ignored">Diabaikan</option><option value="linked">Terhubung ke prospek</option></select></label></section>
        {contacts.data?.length ? <div className="wa951-list">{contacts.data.map(lead => <LeadCard key={lead.id} lead={lead} salesUsers={salesUsers}/>)}</div> : <section className="panel wa951-empty"><Icon name="whatsapp" size={25}/><h3>Belum ada kontak pada filter ini</h3><p>Kontak akan muncul setelah webhook resmi menerima pesan, atau ubah filter untuk melihat data yang sudah ditinjau.</p></section>}
        <nav className="wa951-pagination" aria-label="Navigasi halaman kontak"><span>{contacts.total || 0} kontak · Halaman {contacts.current_page || 1} dari {contacts.last_page || 1}</span><div>{contacts.prev_page_url && <Link className="btn btn-soft btn-sm" href={contacts.prev_page_url}>Sebelumnya</Link>}{contacts.next_page_url && <Link className="btn btn-soft btn-sm" href={contacts.next_page_url}>Berikutnya</Link>}</div></nav>
    </AppLayout>;
}
