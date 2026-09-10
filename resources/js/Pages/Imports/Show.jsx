import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar } from '../../Components/Ui';
import { dateTime } from '../../Utils/format';

const modeLabels={file:'Ikuti Account Executive dari Excel',single:'Satu Account Executive',round_robin:'Bagi Rata ke AE',unassigned:'Belum Ditugaskan'};

export default function Show({ batch }) {
    const distribution=batch.assignment_summary||{};
    return <AppLayout title="Hasil Import" subtitle={batch.filename} action={<div className="action-group"><Link href="/imports" className="btn btn-secondary"><Icon name="upload" size={16}/>Import Lagi</Link><Link href={`/prospects?import_batch=${batch.id}`} className="btn btn-primary"><Icon name="building" size={16}/>Lihat Prospek Import</Link></div>}><Head title="Hasil Import"/>
        <section className="import-result-card panel"><div className="result-icon"><Icon name={batch.error_rows?'alert':'check'} size={28}/></div><div><span className="eyebrow">Import selesai</span><h2>{batch.filename}</h2><p>Diproses oleh {batch.importer?.name||'Admin'} · {dateTime(batch.created_at)} · Assignment: {modeLabels[batch.assignment_mode]||batch.assignment_mode}</p></div></section>
        <section className="import-stat-grid"><div><span>Total</span><strong>{batch.total_rows}</strong></div><div className="success"><span>Prospek baru</span><strong>{batch.imported_rows}</strong></div><div><span>Diperbarui</span><strong>{batch.updated_rows}</strong></div><div className="warning"><span>Dilewati</span><strong>{batch.skipped_rows}</strong></div><div className="danger"><span>Error</span><strong>{batch.error_rows}</strong></div></section>

        <section className="panel distribution-panel"><div className="panel-head"><div><span className="eyebrow">Distribusi Assignment</span><h3>Prospek masuk ke Account Executive berikut</h3><p>Account Executive yang menerima prospek otomatis mendapatkan notifikasi di CRM.</p></div><Link href={`/prospects?import_batch=${batch.id}`} className="btn btn-secondary btn-sm">Lihat Semua</Link></div>
            <div className="distribution-grid">{distribution.sales?.map((item)=><div className="distribution-card" key={item.user_id}><Avatar name={item.name}/><div><strong>{item.name}</strong><small>{item.email}</small></div><span>{item.count}<small> prospek</small></span></div>)}
                <div className={`distribution-card ${distribution.unassigned?'warning':''}`}><span className="unassigned-avatar"><Icon name="building" size={18}/></span><div><strong>Belum Ditugaskan</strong><small>Perlu assignment Admin</small></div><span>{distribution.unassigned||0}<small> prospek</small></span></div>
            </div>
        </section>

        {batch.errors?.length>0&&<section className="panel"><div className="panel-head"><div><span className="eyebrow">Error detail</span><h3>Baris yang tidak diimport</h3></div></div><div className="error-list">{batch.errors.map((e,i)=><div key={i}><strong>Baris {e.row}</strong><p>{e.messages?.join(' · ')}</p></div>)}</div></section>}
    </AppLayout>;
}
