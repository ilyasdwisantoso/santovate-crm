import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { money } from '../../Utils/format';

const toLocal=(v)=>v?new Date(v).toISOString().slice(0,16):'';
const nullableSelect=(v)=>v===true||v===1?'1':v===false||v===0?'0':'';

function Field({ label, error, hint, span=false, children }) { return <label className={`field ${span?'span-2':''}`}><span>{label}</span>{children}{hint&&<small className="field-hint">{hint}</small>}{error&&<small className="field-error">{error}</small>}</label>; }
function ScorePicker({ label, value, onChange, description }) { return <div className="score-picker"><div><strong>{label}</strong><small>{description}</small></div><div className="score-options">{[0,1,2,3].map(n=><button type="button" key={n} className={Number(value)===n?'active':''} onClick={()=>onChange(n)}>{n}</button>)}</div></div>; }

export default function Form({ prospect, mode, statuses, salesUsers }) {
    const { auth }=usePage().props;
    const editing=mode==='edit';
    const { data,setData,post,put,processing,errors }=useForm({
        company_name:prospect.company_name||'',website:prospect.website||'',city:prospect.city||'',service:prospect.service||'',route:prospect.route||'',company_size:prospect.company_size||'',
        contact_name:prospect.contact_name||'',contact_position:prospect.contact_position||'',phone:prospect.phone||'',email:prospect.email||'',current_system:prospect.current_system||'',tracking_portal:nullableSelect(prospect.tracking_portal),
        pain_hypothesis:prospect.pain_hypothesis||'',fit_score:Number(prospect.fit_score||0),pain_score:Number(prospect.pain_score||0),contact_score:Number(prospect.contact_score||0),status:prospect.status||'baru',
        last_contact_at:toLocal(prospect.last_contact_at),next_follow_up_at:toLocal(prospect.next_follow_up_at),source_name:prospect.source_name||'',source_url:prospect.source_url||'',notes:prospect.notes||'',
        estimated_deal_value:Number(prospect.estimated_deal_value||0),actual_deal_value:prospect.actual_deal_value??'',assigned_to:prospect.assigned_user?.id||prospect.assigned_to||'',
    });
    const submit=(e)=>{e.preventDefault(); editing?put(`/prospects/${prospect.id}`):post('/prospects');};
    const total=Number(data.fit_score)+Number(data.pain_score)+Number(data.contact_score);
    const priority=total>=7?'Tinggi':total>=4?'Sedang':'Rendah';

    return <AppLayout title={editing?'Edit Prospek':'Tambah Prospek'} subtitle={editing?'Perbarui informasi, scoring, dan kepemilikan prospek.':'Masukkan informasi inti. Data bisa dilengkapi bertahap setelah riset.'} action={<Link href={editing?`/prospects/${prospect.id}`:'/prospects'} className="btn btn-secondary"><Icon name="arrowLeft" size={17}/>Kembali</Link>}>
        <Head title={editing?'Edit Prospek':'Tambah Prospek'}/>
        <form onSubmit={submit} className="form-layout">
            <div className="form-main">
                <section className="panel form-section"><div className="section-head"><span className="section-number">01</span><div><h3>Profil perusahaan</h3><p>Identitas dan jenis bisnis calon client.</p></div></div><div className="form-grid">
                    <Field label="Nama Perusahaan *" error={errors.company_name}><input value={data.company_name} onChange={e=>setData('company_name',e.target.value)} placeholder="PT Contoh Logistik"/></Field>
                    <Field label="Kota" error={errors.city}><input value={data.city} onChange={e=>setData('city',e.target.value)} placeholder="Jakarta"/></Field>
                    <Field label="Website" error={errors.website}><input value={data.website} onChange={e=>setData('website',e.target.value)} placeholder="https://..."/></Field>
                    <Field label="Ukuran Perusahaan" error={errors.company_size}><input value={data.company_size} onChange={e=>setData('company_size',e.target.value)} placeholder="10–50 karyawan"/></Field>
                    <Field label="Layanan" error={errors.service}><input value={data.service} onChange={e=>setData('service',e.target.value)} placeholder="Freight Forwarding, FCL/LCL"/></Field>
                    <Field label="Rute / Market" error={errors.route}><input value={data.route} onChange={e=>setData('route',e.target.value)} placeholder="China – Indonesia"/></Field>
                </div></section>

                <section className="panel form-section"><div className="section-head"><span className="section-number">02</span><div><h3>Kontak & kondisi saat ini</h3><p>PIC yang bisa dihubungi dan indikasi digitalisasi perusahaan.</p></div></div><div className="form-grid">
                    <Field label="Nama PIC" error={errors.contact_name}><input value={data.contact_name} onChange={e=>setData('contact_name',e.target.value)} placeholder="Nama owner / manager"/></Field>
                    <Field label="Jabatan PIC" error={errors.contact_position}><input value={data.contact_position} onChange={e=>setData('contact_position',e.target.value)} placeholder="Director / Operation Manager"/></Field>
                    <Field label="Telepon / WhatsApp" error={errors.phone}><input value={data.phone} onChange={e=>setData('phone',e.target.value)} placeholder="+62..."/></Field>
                    <Field label="Email" error={errors.email}><input type="email" value={data.email} onChange={e=>setData('email',e.target.value)} placeholder="business@company.com"/></Field>
                    <Field label="Sistem Saat Ini" error={errors.current_system}><input value={data.current_system} onChange={e=>setData('current_system',e.target.value)} placeholder="Excel / WhatsApp / Unknown"/></Field>
                    <Field label="Punya Tracking Portal?" error={errors.tracking_portal}><select value={data.tracking_portal} onChange={e=>setData('tracking_portal',e.target.value)}><option value="">Belum diketahui</option><option value="1">Ya</option><option value="0">Tidak</option></select></Field>
                    <Field label="Dugaan Masalah" span error={errors.pain_hypothesis}><textarea rows="4" value={data.pain_hypothesis} onChange={e=>setData('pain_hypothesis',e.target.value)} placeholder="Contoh: customer update masih lewat WhatsApp dan status shipment direkap manual..."/></Field>
                </div></section>

                <section className="panel form-section"><div className="section-head"><span className="section-number">03</span><div><h3>Account Executive & sumber data</h3><p>Ownership, follow-up, nilai peluang, dan provenance data.</p></div></div><div className="form-grid">
                    <Field label="Status" error={errors.status}><select value={data.status} onChange={e=>setData('status',e.target.value)}>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></Field>
                    {auth.user.is_admin&&<Field label="Account Executive" error={errors.assigned_to}><select value={data.assigned_to} onChange={e=>setData('assigned_to',e.target.value)}><option value="">Belum diassign</option>{salesUsers.map(u=><option value={u.id} key={u.id}>{u.name} · {u.role}</option>)}</select></Field>}
                    <Field label="Follow-up Berikutnya" error={errors.next_follow_up_at}><input type="datetime-local" value={data.next_follow_up_at} onChange={e=>setData('next_follow_up_at',e.target.value)}/></Field>
                    <Field label="Potensi Deal" error={errors.estimated_deal_value} hint={money(data.estimated_deal_value)}><input type="number" min="0" step="100000" value={data.estimated_deal_value} onChange={e=>setData('estimated_deal_value',e.target.value)}/></Field>
                    {editing&&<Field label="Nilai Deal Aktual" error={errors.actual_deal_value} hint={data.actual_deal_value!==''?money(data.actual_deal_value):'Isi setelah deal jika berbeda dari estimasi.'}><input type="number" min="0" step="100000" value={data.actual_deal_value} onChange={e=>setData('actual_deal_value',e.target.value)}/></Field>}
                    <Field label="Nama Sumber" error={errors.source_name}><input value={data.source_name} onChange={e=>setData('source_name',e.target.value)} placeholder="FIATA / Website / Referral"/></Field>
                    <Field label="URL Sumber" error={errors.source_url}><input value={data.source_url} onChange={e=>setData('source_url',e.target.value)} placeholder="https://..."/></Field>
                    <Field label="Catatan Internal" span error={errors.notes}><textarea rows="4" value={data.notes} onChange={e=>setData('notes',e.target.value)} placeholder="Catatan untuk Account Executive..."/></Field>
                </div></section>
            </div>

            <aside className="form-side">
                <section className="panel score-panel"><span className="eyebrow">Lead scoring</span><div className="score-summary"><div><strong>{total}<small>/9</small></strong><span>Total skor</span></div><span className={`priority-large priority-${priority.toLowerCase()}`}>{priority}</span></div>
                    <ScorePicker label="Kecocokan" value={data.fit_score} onChange={v=>setData('fit_score',v)} description="Seberapa cocok dengan ICP Santovate?"/>
                    <ScorePicker label="Masalah Terlihat" value={data.pain_score} onChange={v=>setData('pain_score',v)} description="Seberapa kuat indikasi pain yang bisa kita bantu?"/>
                    <ScorePicker label="Mudah Dihubungi" value={data.contact_score} onChange={v=>setData('contact_score',v)} description="Apakah PIC / decision maker bisa dijangkau?"/>
                    <div className="score-legend"><span>0–3 Rendah</span><span>4–6 Sedang</span><span>7–9 Tinggi</span></div>
                </section>
                <button className="btn btn-primary btn-block btn-lg" disabled={processing}>{processing?'Menyimpan...':editing?'Simpan Perubahan':'Buat Prospek'}</button>
                <p className="form-save-note">Scoring dihitung otomatis. Fokus pada bukti yang Anda temukan saat riset dan discovery.</p>
            </aside>
        </form>
    </AppLayout>;
}
