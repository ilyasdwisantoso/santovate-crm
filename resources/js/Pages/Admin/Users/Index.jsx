import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Icon from '../../../Components/Icon';
import { Avatar, Badge } from '../../../Components/Ui';
import { dateTime } from '../../../Utils/format';

const roleLabel=(role)=>role==='admin'?'Administrator':role==='finance'?'Finance':'Account Executive';
const roleTone=(role)=>role==='admin'?'violet':role==='finance'?'warning':'info';
const limitText=(r)=>r?.unlimited?'Tidak terbatas':`${Number(r?.used||0).toLocaleString('id-ID')} / ${Number(r?.limit||0).toLocaleString('id-ID')}`;

export default function Index({ users, entitlements }) {
    const seats=entitlements?.limits?.users;
    return <AppLayout title="Tim & Access" subtitle="Kelola akun, role, status akses, dan kapasitas seat workspace." action={<Link href="/admin/users/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah User</Link>}><Head title="Tim & Access"/>
        {seats&&<section className="panel entitlement-summary-card"><div><span className="eyebrow">Effective entitlement</span><h3>Seat pengguna</h3><p>Hanya user aktif yang memakai seat. User nonaktif tidak mengurangi kapasitas.</p></div><div className="entitlement-capacity"><strong>{limitText(seats)}</strong><span>{seats.unlimited?'Internal workspace':`${seats.remaining} seat tersisa`}</span><div className="entitlement-meter"><i style={{width:`${seats.usage_percent||0}%`}}/></div></div></section>}
        <section className="panel no-pad"><div className="clean-table-wrap"><table className="clean-table"><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Prospek di-handle</th><th>Login terakhir</th><th></th></tr></thead><tbody>{users.data.map(u=><tr key={u.id}><td><div className="user-cell"><Avatar name={u.name}/><div><strong>{u.name}</strong><span>{u.email}</span></div></div></td><td><Badge tone={roleTone(u.role)}>{roleLabel(u.role)}</Badge></td><td><Badge tone={u.is_active?'success':'neutral'}>{u.is_active?'Aktif':'Nonaktif'}</Badge></td><td><strong>{u.assigned_prospects_count}</strong> perusahaan</td><td>{dateTime(u.last_login_at)}</td><td><Link href={`/admin/users/${u.id}/edit`} className="icon-button"><Icon name="edit" size={16}/></Link></td></tr>)}</tbody></table></div>{!users.data.length&&<div className="mini-empty padded">Belum ada user.</div>}</section>
    </AppLayout>;
}
