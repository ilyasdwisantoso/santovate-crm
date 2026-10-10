import { Head, useForm } from '@inertiajs/react';
import PublicShell from '../Public/PublicShell';

export default function AcceptTeamInvitation({ token, invitation }) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });
    const submit = (event) => {
        event.preventDefault();
        form.post(`/team-invite/${encodeURIComponent(token)}/accept`, { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return <PublicShell title="Aktivasi Akun Tim Santovate CRM">
        <Head title="Terima Undangan Tim"><meta name="robots" content="noindex,nofollow"/><meta name="referrer" content="no-referrer"/></Head>
        <section className="sv-invite-public">
            <div className="sv-invite-icon">S</div>
            <span className="eyebrow">Team invitation · Santovate CRM</span>
            <h1>Selamat datang di {invitation.organization_name}</h1>
            <p>Anda diundang sebagai <strong>{invitation.role === 'admin' ? 'Administrator' : 'Account Executive'}</strong>.
                Buat password untuk mengaktifkan akun Anda.</p>
            <div className="sv-invite-mail"><small>Email undangan</small><strong>{invitation.email}</strong></div>
            <form onSubmit={submit}>
                <label className="field"><span>Nama lengkap *</span>
                    <input autoComplete="name" maxLength="255" value={form.data.name} onChange={e => form.setData('name', e.target.value)} required/>
                    {form.errors.name && <small className="field-error">{form.errors.name}</small>}
                </label>
                <label className="field"><span>Password baru *</span>
                    <input type="password" autoComplete="new-password" minLength="10" value={form.data.password} onChange={e => form.setData('password', e.target.value)} required/>
                    {form.errors.password && <small className="field-error">{form.errors.password}</small>}
                </label>
                <label className="field"><span>Konfirmasi password *</span>
                    <input type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} required/>
                </label>
                <small className="sv-invite-hint">Minimal 10 karakter, mengandung huruf dan angka. Tautan berlaku sekali dan berakhir {new Date(invitation.expires_at).toLocaleString('id-ID')}.</small>
                {Object.keys(form.errors).length > 0 && <p className="field-error">{Object.values(form.errors)[0]}</p>}
                <button className="btn btn-primary" type="submit" disabled={form.processing}>{form.processing ? 'Mengaktifkan…' : 'Aktifkan Akun'}</button>
            </form>
            <small className="sv-invite-footer">Undangan ini hanya untuk alamat email di atas. Jangan membagikan tautannya.</small>
        </section>
    </PublicShell>;
}
