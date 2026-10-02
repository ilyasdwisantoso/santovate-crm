import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Icon from '../../../Components/Icon';
import { Avatar, Badge } from '../../../Components/Ui';
import { dateTime } from '../../../Utils/format';

const roleLabel=(role)=>role==='admin'?'Administrator':'Account Executive';
const roleTone=(role)=>role==='admin'?'violet':'info';
const limitText=(r)=>r?.unlimited?'Tidak terbatas':`${Number(r?.used||0).toLocaleString('id-ID')} / ${Number(r?.limit||0).toLocaleString('id-ID')}`;

function LifecycleModal({ state, replacementUsers, close }) {
    const { errors={} }=usePage().props;
    const user=state?.user;
    const mode=state?.mode;
    const [replacementId,setReplacementId]=useState('');
    const [confirmation,setConfirmation]=useState('');
    const [processing,setProcessing]=useState(false);
    if(!user||!mode)return null;

    const lifecycle=user.lifecycle||{};
    const replacements=(replacementUsers||[]).filter(x=>Number(x.id)!==Number(user.id));
    const needsReassignment=Boolean(lifecycle.requires_reassignment);
    const canDelete=Boolean(lifecycle.can_delete);

    const deactivate=()=>{
        if(needsReassignment&&!replacementId)return;
        setProcessing(true);
        router.post(`/admin/users/${user.id}/deactivate`,{replacement_user_id:replacementId||null},{
            preserveScroll:true,
            onFinish:()=>setProcessing(false),
            onSuccess:close,
        });
    };

    const destroy=()=>{
        setProcessing(true);
        router.visit(`/admin/users/${user.id}`,{
            method:'delete',
            data:{confirmation},
            preserveScroll:true,
            onFinish:()=>setProcessing(false),
            onSuccess:close,
        });
    };

    return <div className="modal-backdrop account-lifecycle-backdrop" onClick={close}>
        <section className="account-lifecycle-modal" onClick={e=>e.stopPropagation()}>
            <div className="modal-head">
                <div>
                    <span className="eyebrow">Account lifecycle</span>
                    <h3>{mode==='delete'?'Hapus akun':'Nonaktifkan akun'}</h3>
                    <p>{user.name} · {user.email}</p>
                </div>
                <button type="button" className="icon-button" onClick={close}><Icon name="close"/></button>
            </div>

            {mode==='deactivate' ? <>
                <div className="account-safety-note">
                    <Icon name="alert" size={20}/>
                    <div><strong>Histori CRM tetap dipertahankan</strong><span>Akun tidak bisa login dan tidak memakai seat. Workload aktif dapat dipindahkan ke Account Executive lain.</span></div>
                </div>

                <div className="account-lifecycle-stats">
                    <div><span>Prospek assigned</span><strong>{lifecycle.workload?.assigned_prospects||0}</strong></div>
                    <div><span>Opportunity aktif</span><strong>{lifecycle.workload?.open_opportunities||0}</strong></div>
                    <div><span>Referensi histori</span><strong>{lifecycle.history_total||0}</strong></div>
                </div>

                {needsReassignment&&<label className="field account-reassign-field">
                    <span>Pindahkan workload aktif ke *</span>
                    <select value={replacementId} onChange={e=>setReplacementId(e.target.value)}>
                        <option value="">Pilih Account Executive pengganti</option>
                        {replacements.map(x=><option key={x.id} value={x.id}>{x.name} · {x.email}</option>)}
                    </select>
                    {!replacements.length&&<small className="field-error">Tidak ada Account Executive aktif lain. Tambahkan atau aktifkan AE terlebih dahulu.</small>}
                    {errors.replacement_user_id&&<small className="field-error">{errors.replacement_user_id}</small>}
                </label>}

                {!lifecycle.can_deactivate&&<div className="account-blocker-list"><strong>Akun ini dilindungi</strong>{(lifecycle.blockers||[]).map((x,i)=><span key={i}>{x}</span>)}</div>}
                {errors.user&&<div className="alert alert-error">{errors.user}</div>}

                <div className="modal-actions account-modal-actions">
                    <button type="button" className="btn btn-secondary" onClick={close}>Batal</button>
                    <button type="button" className="btn btn-primary" disabled={processing||!lifecycle.can_deactivate||(needsReassignment&&(!replacementId||!replacements.length))} onClick={deactivate}>
                        {processing?'Memproses…':'Nonaktifkan akun'}
                    </button>
                </div>
            </> : <>
                {canDelete ? <>
                    <div className="account-danger-note">
                        <Icon name="trash" size={20}/>
                        <div><strong>Hapus permanen akun kosong</strong><span>Tindakan ini tidak dapat dibatalkan. Hanya akun nonaktif tanpa histori CRM yang boleh dihapus.</span></div>
                    </div>
                    <label className="field">
                        <span>Ketik email <b>{user.email}</b> untuk konfirmasi</span>
                        <input value={confirmation} onChange={e=>setConfirmation(e.target.value)} placeholder={user.email}/>
                        {errors.confirmation&&<small className="field-error">{errors.confirmation}</small>}
                    </label>
                    <div className="modal-actions account-modal-actions">
                        <button type="button" className="btn btn-secondary" onClick={close}>Batal</button>
                        <button type="button" className="btn account-danger-button" disabled={processing||confirmation.trim().toLowerCase()!==String(user.email).toLowerCase()} onClick={destroy}>
                            {processing?'Menghapus…':'Hapus permanen'}
                        </button>
                    </div>
                </> : <>
                    <div className="account-danger-note muted-danger">
                        <Icon name="alert" size={20}/>
                        <div><strong>Tidak aman untuk hard delete</strong><span>Akun ini masih aktif, dilindungi, atau sudah memiliki histori CRM. Gunakan nonaktifkan agar audit dan histori tetap utuh.</span></div>
                    </div>
                    <div className="account-blocker-list"><strong>Alasan</strong>{(lifecycle.blockers||['Akun memiliki histori CRM.']).map((x,i)=><span key={i}>{x}</span>)}</div>
                    <div className="modal-actions account-modal-actions"><button type="button" className="btn btn-primary" onClick={close}>Mengerti</button></div>
                </>}
            </>}
        </section>
    </div>;
}

function UserActions({ user, open }) {
    const [busy,setBusy]=useState(false);
    const lifecycle=user.lifecycle||{};
    const activate=()=>{
        setBusy(true);
        router.patch(`/admin/users/${user.id}/status`,{is_active:true},{preserveScroll:true,onFinish:()=>setBusy(false)});
    };
    return <div className="account-row-actions">
        <Link href={`/admin/users/${user.id}/edit`} className="icon-button" title="Edit akun"><Icon name="edit" size={16}/></Link>
        {user.is_active
            ? <button type="button" className="icon-button account-disable-action" title="Nonaktifkan akun" disabled={busy||!lifecycle.can_deactivate} onClick={()=>open({mode:'deactivate',user})}><Icon name="close" size={16}/></button>
            : <button type="button" className="icon-button account-enable-action" title="Aktifkan akun" disabled={busy||lifecycle.is_platform_admin} onClick={activate}><Icon name="check" size={16}/></button>}
        <button type="button" className="icon-button account-delete-action" title="Hapus akun" disabled={lifecycle.is_self||lifecycle.is_platform_admin} onClick={()=>open({mode:'delete',user})}><Icon name="trash" size={16}/></button>
    </div>;
}

function MobileUserCard({ user, open }) {
    return <article className="account-mobile-card">
        <div className="account-mobile-head">
            <div className="user-cell"><Avatar name={user.name}/><div><strong>{user.name}</strong><span>{user.email}</span></div></div>
            <Badge tone={user.is_active?'success':'neutral'}>{user.is_active?'Aktif':'Nonaktif'}</Badge>
        </div>
        <div className="account-mobile-meta">
            <div><span>Role</span><Badge tone={roleTone(user.role)}>{roleLabel(user.role)}</Badge></div>
            <div><span>Prospek</span><strong>{user.assigned_prospects_count||0}</strong></div>
            <div><span>Login terakhir</span><strong>{dateTime(user.last_login_at)}</strong></div>
        </div>
        <UserActions user={user} open={open}/>
    </article>;
}

export default function Index({ users, entitlements, replacementUsers=[] }) {
    const seats=entitlements?.limits?.users;
    const [modal,setModal]=useState(null);
    const rows=useMemo(()=>users?.data||[],[users]);

    return <AppLayout title="Tim & Access" subtitle="Kelola akun, role, status akses, workload, dan kapasitas seat workspace." action={<Link href="/admin/users/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah User</Link>}>
        <Head title="Tim & Access"/>
        {seats&&<section className="panel entitlement-summary-card"><div><span className="eyebrow">Effective entitlement</span><h3>Seat pengguna</h3><p>Hanya user aktif yang memakai seat. User nonaktif tidak mengurangi kapasitas.</p></div><div className="entitlement-capacity"><strong>{limitText(seats)}</strong><span>{seats.unlimited?'Internal workspace':`${seats.remaining} seat tersisa`}</span><div className="entitlement-meter"><i style={{width:`${seats.usage_percent||0}%`}}/></div></div></section>}

        <section className="panel no-pad account-user-panel">
            <div className="clean-table-wrap desktop-only"><table className="clean-table account-user-table"><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Prospek di-handle</th><th>Login terakhir</th><th>Aksi</th></tr></thead><tbody>{rows.map(u=><tr key={u.id}><td><div className="user-cell"><Avatar name={u.name}/><div><strong>{u.name}</strong><span>{u.email}</span></div></div></td><td><Badge tone={roleTone(u.role)}>{roleLabel(u.role)}</Badge></td><td><Badge tone={u.is_active?'success':'neutral'}>{u.is_active?'Aktif':'Nonaktif'}</Badge></td><td><strong>{u.assigned_prospects_count}</strong> perusahaan</td><td>{dateTime(u.last_login_at)}</td><td><UserActions user={u} open={setModal}/></td></tr>)}</tbody></table></div>
            <div className="account-mobile-list mobile-only">{rows.map(u=><MobileUserCard key={u.id} user={u} open={setModal}/>)}</div>
            {!rows.length&&<div className="mini-empty padded">Belum ada user.</div>}
        </section>

        {modal&&<LifecycleModal state={modal} replacementUsers={replacementUsers} close={()=>setModal(null)}/>}
    </AppLayout>;
}
