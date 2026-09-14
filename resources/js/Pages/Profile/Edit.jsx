import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Avatar } from '../../Components/Ui';
import Icon from '../../Components/Icon';

export default function ProfileEdit({ profile }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const form = useForm({
        name: profile.name || '',
        email: profile.email || '',
        phone: profile.phone || '',
        job_title: profile.job_title || '',
        department: profile.department || '',
        profile_initials: profile.profile_initials || '',
        whatsapp_signature: profile.whatsapp_signature || '',
        bio: profile.bio || '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.patch('/profile', {
            preserveScroll: true,
            onSuccess: () => form.setData({
                ...form.data,
                password: '',
                password_confirmation: '',
            }),
        });
    };

    return (
        <AppLayout
            title="Profile"
            subtitle="Perbarui identitas Account Executive, kontak, dan preferensi pesan WhatsApp."
        >
            <Head title="Profile"/>

            <section className="profile-shell-v24">
                <aside className="panel profile-preview-v24">
                    <div className="profile-cover-v24"/>
                    <div className="profile-preview-body-v24">
                        <Avatar
                            name={form.data.name}
                            email={form.data.email}
                            initialsText={form.data.profile_initials}
                            size="lg"
                        />
                        <h3>{form.data.name || 'Account Executive'}</h3>
                        <p>{form.data.job_title || (user.is_admin ? 'Administrator' : 'Account Executive')}</p>
                        <span>{form.data.department || 'Santovate Digital Solution'}</span>
                    </div>

                    <div className="profile-preview-meta-v24">
                        <div>
                            <small>Email</small>
                            <strong>{form.data.email || '—'}</strong>
                        </div>
                        <div>
                            <small>WhatsApp</small>
                            <strong>{form.data.phone || 'Belum diisi'}</strong>
                        </div>
                        <div>
                            <small>Inisial profile</small>
                            <strong>{form.data.profile_initials || 'Otomatis dari nama'}</strong>
                        </div>
                    </div>
                </aside>

                <form className="panel profile-form-v24" onSubmit={submit}>
                    <div className="panel-head">
                        <div>
                            <span className="eyebrow">Account Executive profile</span>
                            <h3>Data pribadi & pekerjaan</h3>
                            <p>Data ini digunakan di tampilan profile dan template follow-up WhatsApp.</p>
                        </div>
                    </div>

                    <div className="form-grid profile-grid-v24">
                        <label className="field">
                            <span>Nama lengkap</span>
                            <input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Contoh: Ahmad Mazkur"
                            />
                            {form.errors.name && <small className="field-error">{form.errors.name}</small>}
                        </label>

                        <label className="field">
                            <span>Email</span>
                            <input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                placeholder="nama@santovate.com"
                            />
                            {form.errors.email && <small className="field-error">{form.errors.email}</small>}
                        </label>

                        <label className="field">
                            <span>Nomor WhatsApp</span>
                            <input
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                                placeholder="08xxxxxxxxxx"
                            />
                            {form.errors.phone && <small className="field-error">{form.errors.phone}</small>}
                        </label>

                        <label className="field">
                            <span>Jabatan</span>
                            <input
                                value={form.data.job_title}
                                onChange={(e) => form.setData('job_title', e.target.value)}
                                placeholder="CRM Account Executive"
                            />
                            {form.errors.job_title && <small className="field-error">{form.errors.job_title}</small>}
                        </label>

                        <label className="field">
                            <span>Departemen / Tim</span>
                            <input
                                value={form.data.department}
                                onChange={(e) => form.setData('department', e.target.value)}
                                placeholder="Business Development"
                            />
                            {form.errors.department && <small className="field-error">{form.errors.department}</small>}
                        </label>

                        <label className="field">
                            <span>Inisial Profile</span>
                            <input
                                value={form.data.profile_initials}
                                onChange={(e) => form.setData('profile_initials', e.target.value.toUpperCase().slice(0, 2))}
                                placeholder="AM"
                                maxLength={2}
                            />
                            <small className="field-hint">Maksimal 2 huruf. Jika kosong, CRM mengambil inisial dari nama.</small>
                            {form.errors.profile_initials && <small className="field-error">{form.errors.profile_initials}</small>}
                        </label>
                    </div>

                    <label className="field">
                        <span>Signature WhatsApp</span>
                        <textarea
                            rows="4"
                            value={form.data.whatsapp_signature}
                            onChange={(e) => form.setData('whatsapp_signature', e.target.value)}
                            placeholder={'Contoh:\nAhmad Mazkur\nCRM Account Executive · Santovate Digital Solution'}
                        />
                        <small className="field-hint">Signature otomatis ditambahkan ke draft template jika belum ada.</small>
                        {form.errors.whatsapp_signature && <small className="field-error">{form.errors.whatsapp_signature}</small>}
                    </label>

                    <label className="field">
                        <span>Bio singkat</span>
                        <textarea
                            rows="4"
                            value={form.data.bio}
                            onChange={(e) => form.setData('bio', e.target.value)}
                            placeholder="Fokus industri, area tanggung jawab, atau catatan internal."
                        />
                        {form.errors.bio && <small className="field-error">{form.errors.bio}</small>}
                    </label>

                    <div className="profile-password-block-v24">
                        <div className="section-title-v24">
                            <span className="section-icon-v24"><Icon name="target" size={18}/></span>
                            <div>
                                <strong>Ubah password</strong>
                                <small>Kosongkan jika password tidak ingin diganti.</small>
                            </div>
                        </div>

                        <div className="form-grid profile-grid-v24">
                            <label className="field">
                                <span>Password baru</span>
                                <input
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    autoComplete="new-password"
                                />
                                {form.errors.password && <small className="field-error">{form.errors.password}</small>}
                            </label>

                            <label className="field">
                                <span>Konfirmasi password</span>
                                <input
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                    autoComplete="new-password"
                                />
                            </label>
                        </div>
                    </div>

                    <div className="profile-actions-v24">
                        <button className="btn btn-primary" disabled={form.processing}>
                            <Icon name="check" size={17}/>
                            {form.processing ? 'Menyimpan...' : 'Simpan Profile'}
                        </button>
                    </div>
                </form>
            </section>
        </AppLayout>
    );
}
