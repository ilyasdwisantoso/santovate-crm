import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Icon from '../../../Components/Icon';
import { Avatar, Badge } from '../../../Components/Ui';
import { dateTime } from '../../../Utils/format';

export default function Index({ users }) {
    return <AppLayout title="Tim Account Executive" subtitle="Kelola akun, role, status akses, dan beban prospek tim." action={<Link href="/admin/users/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah User</Link>}><Head title="Tim Account Executive"/>
        <section className="panel no-pad"><div className="clean-table-wrap"><table className="clean-table"><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Prospek di-handle</th><th>Login terakhir</th><th></th></tr></thead><tbody>{users.data.map(u=><tr key={u.id}><td><div className="user-cell"><Avatar name={u.name}/><div><strong>{u.name}</strong><span>{u.email}</span></div></div></td><td><Badge tone={u.role==='admin'?'violet':'info'}>{u.role==='admin'?'Administrator':'Account Executive'}</Badge></td><td><Badge tone={u.is_active?'success':'neutral'}>{u.is_active?'Aktif':'Nonaktif'}</Badge></td><td><strong>{u.assigned_prospects_count}</strong> perusahaan</td><td>{dateTime(u.last_login_at)}</td><td><Link href={`/admin/users/${u.id}/edit`} className="icon-button"><Icon name="edit" size={16}/></Link></td></tr>)}</tbody></table></div>{!users.data.length&&<div className="mini-empty padded">Belum ada user.</div>}</section>
    </AppLayout>;
}
