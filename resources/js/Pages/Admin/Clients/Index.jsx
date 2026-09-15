import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

const rupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

export default function ClientOnboarding({ plans = [], configurations = [], clients = [] }) {
    const form = useForm({
        business_name: '',
        owner_name: '',
        owner_email: '',
        owner_phone: '',
        password: '',
        password_confirmation: '',
        plan_key: plans[0]?.key || '',
        configuration_key: configurations[0]?.key || '',
        billing_cycle: 'monthly',
        payment_reference: '',
    });

    const selectedPlan = plans.find((plan) => plan.key === form.data.plan_key);
    const selectedConfig = configurations.find((config) => config.key === form.data.configuration_key);
    const base = form.data.billing_cycle === 'annual' ? selectedPlan?.annual_price : selectedPlan?.monthly_price;
    const addon = form.data.billing_cycle === 'annual' ? selectedConfig?.annual_addon_price : selectedConfig?.monthly_addon_price;
    const total = Number(base || 0) + Number(addon || 0);

    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/clients', {
            preserveScroll: true,
            onSuccess: () => form.reset('business_name', 'owner_name', 'owner_email', 'owner_phone', 'password', 'password_confirmation', 'payment_reference'),
        });
    };

    return (
        <AppLayout
            title="Client Onboarding"
            subtitle="Aktifkan workspace client yang pembayarannya sudah Anda konfirmasi."
        >
            <Head title="Client Onboarding"/>

            <section className="sv-onboarding-grid">
                <form className="panel sv-settings-form" onSubmit={submit}>
                    <div className="panel-head">
                        <div>
                            <span className="eyebrow">Paid client activation</span>
                            <h3>Buat workspace + akun owner</h3>
                            <p>Gunakan form ini hanya setelah pembayaran client benar-benar diterima atau diverifikasi.</p>
                        </div>
                    </div>

                    <div className="form-grid two">
                        <label className="field">
                            <span>Nama bisnis</span>
                            <input value={form.data.business_name} onChange={(e) => form.setData('business_name', e.target.value)} placeholder="PT Contoh Indonesia"/>
                            {form.errors.business_name && <small className="text-danger">{form.errors.business_name}</small>}
                        </label>
                        <label className="field">
                            <span>Nama owner/admin</span>
                            <input value={form.data.owner_name} onChange={(e) => form.setData('owner_name', e.target.value)} placeholder="Nama client"/>
                            {form.errors.owner_name && <small className="text-danger">{form.errors.owner_name}</small>}
                        </label>
                        <label className="field">
                            <span>Email login client</span>
                            <input type="email" value={form.data.owner_email} onChange={(e) => form.setData('owner_email', e.target.value)} placeholder="owner@client.com"/>
                            {form.errors.owner_email && <small className="text-danger">{form.errors.owner_email}</small>}
                        </label>
                        <label className="field">
                            <span>No. WhatsApp client</span>
                            <input value={form.data.owner_phone} onChange={(e) => form.setData('owner_phone', e.target.value)} placeholder="0812..."/>
                        </label>
                        <label className="field">
                            <span>Password awal</span>
                            <input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password"/>
                            {form.errors.password && <small className="text-danger">{form.errors.password}</small>}
                        </label>
                        <label className="field">
                            <span>Konfirmasi password</span>
                            <input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} autoComplete="new-password"/>
                        </label>
                        <label className="field">
                            <span>Paket</span>
                            <select value={form.data.plan_key} onChange={(e) => form.setData('plan_key', e.target.value)}>
                                {plans.map((plan) => (
                                    <option key={plan.key} value={plan.key}>{plan.name} · {plan.user_limit} user</option>
                                ))}
                            </select>
                        </label>
                        <label className="field">
                            <span>Konfigurasi bisnis</span>
                            <select value={form.data.configuration_key} onChange={(e) => form.setData('configuration_key', e.target.value)}>
                                {configurations.map((config) => (
                                    <option key={config.key} value={config.key}>{config.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="field">
                            <span>Billing</span>
                            <select value={form.data.billing_cycle} onChange={(e) => form.setData('billing_cycle', e.target.value)}>
                                <option value="monthly">Bulanan</option>
                                <option value="annual">Tahunan</option>
                            </select>
                        </label>
                        <label className="field">
                            <span>Referensi pembayaran</span>
                            <input value={form.data.payment_reference} onChange={(e) => form.setData('payment_reference', e.target.value)} placeholder="Opsional, mis. INV-2026-001"/>
                            {form.errors.payment_reference && <small className="text-danger">{form.errors.payment_reference}</small>}
                        </label>
                    </div>

                    <div className="sv-onboarding-price">
                        <span>Base package <b>{rupiah(base)}</b></span>
                        <span>Business configuration <b>{rupiah(addon)}</b></span>
                        <strong>Total {form.data.billing_cycle === 'annual' ? '/ tahun' : '/ bulan'} <b>{rupiah(total)}</b></strong>
                    </div>

                    <button className="btn btn-primary" disabled={form.processing}>
                        {form.processing ? 'Mengaktifkan...' : 'Aktifkan Client'}
                    </button>
                </form>

                <aside className="panel sv-onboarding-flow">
                    <span className="eyebrow">Mekanisme</span>
                    <h3>Setelah client membeli</h3>
                    <ol>
                        <li><b>1</b><span>Konfirmasi pembayaran yang masuk.</span></li>
                        <li><b>2</b><span>Pilih paket dan konfigurasi bisnis yang dibeli.</span></li>
                        <li><b>3</b><span>Buat akun owner/admin client melalui form ini.</span></li>
                        <li><b>4</b><span>Sistem membuat workspace terpisah dan subscription aktif.</span></li>
                        <li><b>5</b><span>Kirim email login serta password awal kepada client.</span></li>
                        <li><b>6</b><span>Client login, lalu admin client dapat membuat akun sales sesuai user limit paket.</span></li>
                    </ol>
                </aside>
            </section>

            <section className="panel">
                <div className="panel-head">
                    <div>
                        <span className="eyebrow">Workspace registry</span>
                        <h3>Client & workspace terbaru</h3>
                    </div>
                </div>

                <div className="sv-client-table-wrap">
                    <table className="sv-client-table">
                        <thead>
                            <tr>
                                <th>Bisnis</th>
                                <th>Owner</th>
                                <th>Konfigurasi</th>
                                <th>Paket</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.map((client) => (
                                <tr key={client.id}>
                                    <td><strong>{client.name}</strong><small>{client.slug}{client.is_demo ? ' · DEMO' : ''}</small></td>
                                    <td><strong>{client.owner?.name || '-'}</strong><small>{client.owner?.email || '-'}</small></td>
                                    <td>{client.configuration?.name || '-'}</td>
                                    <td>
                                        <strong>{client.subscription?.plan?.name || '-'}</strong>
                                        <small>{client.subscription?.billing_cycle || ''}</small>
                                    </td>
                                    <td><span className={`badge ${client.subscription?.status === 'active' ? 'badge-success' : 'badge-warning'}`}>{client.subscription?.status || client.status}</span></td>
                                </tr>
                            ))}
                            {clients.length === 0 && (
                                <tr><td colSpan="5">Belum ada workspace client.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
