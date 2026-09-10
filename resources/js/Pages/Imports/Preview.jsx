import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar } from '../../Components/Ui';
import { money } from '../../Utils/format';

const assignmentOptions = [
    { value:'file', icon:'mail', title:'Ikuti Account Executive dari Excel', description:'Gunakan kolom Email Account Executive, atau Email Sales pada template lama.' },
    { value:'single', icon:'users', title:'Assign semua ke satu Account Executive', description:'Semua data yang diproses diberikan ke satu Account Executive pilihan.' },
    { value:'round_robin', icon:'pipeline', title:'Bagi rata ke Account Executive aktif', description:'Prospek dibagikan bergiliran ke Account Executive yang Anda pilih.' },
    { value:'unassigned', icon:'building', title:'Belum ditugaskan', description:'Import dulu, lalu Admin assign secara manual nanti.' },
];

export default function Preview({ rows, token, extension, originalName, summary, salesUsers }) {
    const form = useForm({
        token,
        extension,
        original_name: originalName,
        duplicate_mode: 'skip',
        assignment_mode: 'file',
        single_sales_id: '',
        round_robin_sales_ids: salesUsers.map((user)=>user.id),
    });

    const selectedRoundRobinSales = useMemo(
        ()=>salesUsers.filter((user)=>form.data.round_robin_sales_ids.includes(user.id)),
        [salesUsers, form.data.round_robin_sales_ids],
    );

    const rowAssignments = useMemo(()=>{
        let roundRobinIndex = 0;
        const result = {};
        rows.forEach((row)=>{
            const processable = row.valid && !(row.duplicate && form.data.duplicate_mode === 'skip');
            if (!processable) {
                result[row.row_number] = { label:'Tidak diproses', muted:true };
                return;
            }

            if (form.data.assignment_mode === 'unassigned') {
                result[row.row_number] = { label:'Belum ditugaskan', warning:true };
                return;
            }

            if (form.data.assignment_mode === 'single') {
                const user = salesUsers.find((sales)=>sales.id === Number(form.data.single_sales_id));
                result[row.row_number] = user ? { user } : { label:'Pilih Account Executive dahulu', warning:true };
                return;
            }

            if (form.data.assignment_mode === 'round_robin') {
                if (!selectedRoundRobinSales.length) {
                    result[row.row_number] = { label:'Pilih minimal 1 Account Executive', warning:true };
                    return;
                }
                const user = selectedRoundRobinSales[roundRobinIndex % selectedRoundRobinSales.length];
                roundRobinIndex += 1;
                result[row.row_number] = { user };
                return;
            }

            if (row.file_sales) {
                result[row.row_number] = { user:row.file_sales };
            } else if (row.duplicate && form.data.duplicate_mode === 'update' && row.existing_sales) {
                result[row.row_number] = { user:row.existing_sales, note:'Account Executive lama dipertahankan' };
            } else {
                result[row.row_number] = {
                    label: row.file_sales_warning ? 'Email Account Executive tidak ditemukan' : 'Belum ditugaskan',
                    warning:true,
                };
            }
        });
        return result;
    }, [rows, form.data.assignment_mode, form.data.duplicate_mode, form.data.single_sales_id, selectedRoundRobinSales, salesUsers]);

    const assignmentStats = useMemo(()=>{
        const stats = new Map();
        let unassigned = 0;
        Object.values(rowAssignments).forEach((assignment)=>{
            if (assignment?.user) {
                stats.set(assignment.user.id, { user:assignment.user, count:(stats.get(assignment.user.id)?.count || 0) + 1 });
            } else if (assignment?.warning && assignment.label !== 'Pilih Account Executive dahulu' && assignment.label !== 'Pilih minimal 1 Account Executive') {
                unassigned += 1;
            }
        });
        return { sales:[...stats.values()], unassigned };
    }, [rowAssignments]);

    const toggleRoundRobin = (id)=>{
        const current = form.data.round_robin_sales_ids;
        form.setData('round_robin_sales_ids', current.includes(id) ? current.filter((item)=>item!==id) : [...current,id]);
    };

    const submit = (event)=>{
        event.preventDefault();
        form.post('/imports/commit');
    };

    return <AppLayout title="Preview Import" subtitle={`Periksa ${originalName} dan tentukan Account Executive tujuan sebelum data masuk ke CRM.`} action={<button className="btn btn-secondary" onClick={()=>router.get('/imports')}><Icon name="arrowLeft" size={17}/>Upload Ulang</button>}>
        <Head title="Preview Import"/>

        <section className="import-stat-grid">
            <div><span>Total baris</span><strong>{summary.total}</strong></div>
            <div className="success"><span>Valid</span><strong>{summary.valid}</strong></div>
            <div className="warning"><span>Duplikat</span><strong>{summary.duplicates}</strong></div>
            <div className="danger"><span>Error</span><strong>{summary.errors}</strong></div>
        </section>

        <form onSubmit={submit}>
            <section className="panel assignment-panel">
                <div className="panel-head assignment-head"><div><span className="eyebrow">Assignment Prospect</span><h3>Prospek ini masuk ke Account Executive siapa?</h3><p>Pilih metode penugasan. Kolom <b>Account Executive Tujuan</b> di preview akan berubah sebelum Anda melakukan import.</p></div><span className="step-chip">Wajib dipilih</span></div>

                <div className="assignment-mode-grid">
                    {assignmentOptions.map((option)=><label key={option.value} className={`assignment-mode-card ${form.data.assignment_mode===option.value?'active':''}`}>
                        <input type="radio" name="assignment_mode" value={option.value} checked={form.data.assignment_mode===option.value} onChange={()=>form.setData('assignment_mode',option.value)}/>
                        <span className="assignment-mode-icon"><Icon name={option.icon} size={19}/></span>
                        <span><strong>{option.title}</strong><small>{option.description}</small></span>
                        <span className="radio-dot"/>
                    </label>)}
                </div>

                {form.data.assignment_mode === 'single' && <div className="assignment-config-box">
                    <label className="field compact-field"><span>Pilih Account Executive Tujuan</span><select value={form.data.single_sales_id} onChange={(e)=>form.setData('single_sales_id',Number(e.target.value)||'')}><option value="">Pilih Account Executive...</option>{salesUsers.map((user)=><option key={user.id} value={user.id}>{user.name} — {user.email}</option>)}</select></label>
                </div>}

                {form.data.assignment_mode === 'round_robin' && <div className="assignment-config-box">
                    <div className="assignment-config-title"><div><strong>Pilih Account Executive yang ikut menerima data</strong><small>CRM membagi prospek secara bergiliran hanya ke user yang dicentang.</small></div><span>{selectedRoundRobinSales.length} AE dipilih</span></div>
                    <div className="sales-check-grid">{salesUsers.map((user)=><label key={user.id} className={`sales-check-card ${form.data.round_robin_sales_ids.includes(user.id)?'active':''}`}><input type="checkbox" checked={form.data.round_robin_sales_ids.includes(user.id)} onChange={()=>toggleRoundRobin(user.id)}/><Avatar name={user.name} size="sm"/><span><strong>{user.name}</strong><small>{user.email}</small></span><Icon name="check" size={16}/></label>)}</div>
                </div>}

                {form.errors.assignment && <div className="alert alert-error">{form.errors.assignment}</div>}

                <div className="assignment-summary-strip">
                    <div><span>Prospek yang akan diproses</span><strong>{Object.values(rowAssignments).filter((a)=>!a?.muted).length}</strong></div>
                    {assignmentStats.sales.slice(0,4).map((item)=><div key={item.user.id}><span>{item.user.name}</span><strong>{item.count}</strong></div>)}
                    <div className={assignmentStats.unassigned?'warn':''}><span>Belum ditugaskan</span><strong>{assignmentStats.unassigned}</strong></div>
                </div>
            </section>

            <section className="panel no-pad">
                <div className="preview-toolbar padded"><div><span className="eyebrow">Final Preview</span><h3>Data siap diperiksa</h3><p>Baris error tidak diimport. Duplikat mengikuti pilihan Anda. Account Executive tujuan terlihat langsung per perusahaan.</p></div><div className="commit-form"><label><span>Jika duplikat</span><select value={form.data.duplicate_mode} onChange={(e)=>form.setData('duplicate_mode',e.target.value)}><option value="skip">Lewati data lama</option><option value="update">Update data lama</option></select></label><button className="btn btn-primary" disabled={form.processing}>{form.processing?'Mengimpor...':'Import & Assign ke AE'}<Icon name="upload" size={16}/></button></div></div>
                {form.errors.import && <div className="alert alert-error padded">{form.errors.import}</div>}
                <div className="preview-table-wrap"><table className="prospect-table"><thead><tr><th>Baris</th><th>Status</th><th>Perusahaan</th><th>PIC</th><th>Account Executive Tujuan</th><th>Skor</th><th>Potensi</th><th>Validasi</th></tr></thead><tbody>{rows.map((row)=>{
                    const assignment=rowAssignments[row.row_number];
                    return <tr key={row.row_number} className={!row.valid?'row-error':row.duplicate?'row-warning':''}><td>{row.row_number}</td><td>{!row.valid?<span className="badge badge-danger">Error</span>:row.duplicate?<span className="badge badge-warning">Duplikat</span>:<span className="badge badge-success">Valid</span>}</td><td><div className="stacked-cell"><strong>{row.data.company_name||'—'}</strong><small>{[row.data.city,row.data.service].filter(Boolean).join(' · ')||'Data belum lengkap'}</small></div></td><td><div className="stacked-cell"><strong>{row.data.contact_name||'Belum ada PIC'}</strong><small>{row.data.email||row.data.phone||'—'}</small></div></td><td>{assignment?.user?<div className="user-cell"><Avatar name={assignment.user.name} size="sm"/><div className="stacked-cell"><strong>{assignment.user.name}</strong><small>{assignment.note||assignment.user.email}</small></div></div>:<span className={`assignment-empty ${assignment?.warning?'warning':''}`}>{assignment?.label||'—'}</span>}</td><td>{Number(row.data.fit_score||0)+Number(row.data.pain_score||0)+Number(row.data.contact_score||0)}/9</td><td>{money(row.data.estimated_deal_value)}</td><td>{row.errors?.length?row.errors.join(' · '):row.file_sales_warning&&form.data.assignment_mode==='file'?row.file_sales_warning:row.duplicate?'Perusahaan + kota sudah ada':'Siap diimport'}</td></tr>;
                })}</tbody></table></div>
            </section>
        </form>
    </AppLayout>;
}
