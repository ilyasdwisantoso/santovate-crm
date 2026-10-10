import { useForm } from '@inertiajs/react';
import PublicShell from './PublicShell';
import { registrationPasswordError } from '../../Utils/registrationValidation';

const inputError = (errors, key) => errors[key] || null;

export default function Register({ plans, configurations, selected }) {
    const form = useForm({
        business_name: '', name: '', email: '', phone: '', password: '',
        password_confirmation: '',
        plan_key: selected.plan || plans[0]?.key || 'starter',
        configuration_key: selected.config || configurations[0]?.key || 'software-agency',
        billing_cycle: selected.billing || 'monthly',
    });
    const mismatch = registrationPasswordError(form.data.password, form.data.password_confirmation);
    const passwordError = inputError(form.errors, 'password');
    const confirmationError = inputError(form.errors, 'password_confirmation') || mismatch;

    const change = (key) => (event) => {
        form.setData(key, event.target.value);
        if (form.errors[key]) form.clearErrors(key);
        if (['password', 'password_confirmation'].includes(key)) {
            form.clearErrors('password', 'password_confirmation');
        }
    };
    const submit = (event) => {
        event.preventDefault();
        const error = registrationPasswordError(form.data.password, form.data.password_confirmation);
        if (error) {
            form.setError('password_confirmation', error);
            return;
        }
        form.post('/register', {
            preserveScroll: true,
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    };

    return <PublicShell title="Daftar Santovate CRM">
        <section className="sv-public-hero compact">
            <span>/ CREATE WORKSPACE</span>
            <h1>Buat workspace,<br/><em>aktifkan setelah pembayaran.</em></h1>
        </section>
        <form className="sv-register-card" onSubmit={submit}>
            <div className="sv-form-grid">
                <label>Nama bisnis
                    <input value={form.data.business_name} onChange={change('business_name')} maxLength={180} autoComplete="organization" required aria-invalid={Boolean(form.errors.business_name)}/>
                    {form.errors.business_name && <small className="sv-register-field-error">{form.errors.business_name}</small>}
                </label>
                <label>Nama admin
                    <input value={form.data.name} onChange={change('name')} maxLength={180} autoComplete="name" required aria-invalid={Boolean(form.errors.name)}/>
                    {form.errors.name && <small className="sv-register-field-error">{form.errors.name}</small>}
                </label>
                <label>Email
                    <input type="email" value={form.data.email} onChange={change('email')} maxLength={255} autoComplete="email" required aria-invalid={Boolean(form.errors.email)}/>
                    {form.errors.email && <small className="sv-register-field-error">{form.errors.email}</small>}
                </label>
                <label>WhatsApp
                    <input type="tel" value={form.data.phone} onChange={change('phone')} maxLength={50} autoComplete="tel" required aria-invalid={Boolean(form.errors.phone)}/>
                    {form.errors.phone && <small className="sv-register-field-error">{form.errors.phone}</small>}
                </label>
                <label>Paket
                    <select value={form.data.plan_key} onChange={change('plan_key')} required>{plans.map(x=><option key={x.key} value={x.key}>{x.name}</option>)}</select>
                    {form.errors.plan_key && <small className="sv-register-field-error">{form.errors.plan_key}</small>}
                </label>
                <label>Konfigurasi bisnis
                    <select value={form.data.configuration_key} onChange={change('configuration_key')} required>{configurations.map(x=><option key={x.key} value={x.key}>{x.name}</option>)}</select>
                    {form.errors.configuration_key && <small className="sv-register-field-error">{form.errors.configuration_key}</small>}
                </label>
                <label>Billing
                    <select value={form.data.billing_cycle} onChange={change('billing_cycle')} required>
                        <option value="monthly">Bulanan</option><option value="annual">Tahunan</option>
                    </select>
                    {form.errors.billing_cycle && <small className="sv-register-field-error">{form.errors.billing_cycle}</small>}
                </label>
                <label>Password
                    <input type="password" value={form.data.password} onChange={change('password')}
                        minLength={8} autoComplete="new-password" required aria-invalid={Boolean(passwordError)}/>
                    {passwordError && <small className="sv-register-field-error">{passwordError === 'validation.confirmed' ? 'Konfirmasi password tidak cocok.' : passwordError}</small>}
                    <small className="sv-register-helper">Minimal 8 karakter. Ketik ulang password yang sama pada kolom konfirmasi.</small>
                </label>
                <label>Konfirmasi password
                    <input type="password" value={form.data.password_confirmation} onChange={change('password_confirmation')}
                        minLength={8} autoComplete="new-password" required aria-invalid={Boolean(confirmationError)}/>
                    {confirmationError && <small className="sv-register-field-error">{confirmationError}</small>}
                </label>
            </div>
            {Object.entries(form.errors).filter(([key]) => !['password', 'password_confirmation','business_name','name','email','phone','plan_key','configuration_key','billing_cycle'].includes(key)).map(([key, value]) =>
                <div className="alert alert-error" key={key}>{value}</div>
            )}
            <button className="btn btn-primary" type="submit" disabled={form.processing || Boolean(mismatch)}>
                {form.processing ? 'Memproses…' : 'Buat Workspace & Lanjut Pembayaran'}
            </button>
        </form>
    </PublicShell>;
}
