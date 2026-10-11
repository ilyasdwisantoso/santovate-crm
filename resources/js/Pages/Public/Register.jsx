import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PublicShell from './PublicShell';
import { registrationPasswordError } from '../../Utils/registrationValidation';
import { formatRegistrationRupiah, registrationPriceSummary, resolveRegistrationSelection } from '../../Utils/registrationPricing';

const FIELDS = ['business_name', 'name', 'email', 'phone', 'plan_key', 'configuration_key', 'billing_cycle', 'password', 'password_confirmation'];

function FormField({ id, label, hint, error, children }) {
    return <div className={`sv11-field ${error ? 'sv11-field--error' : ''}`}>
        <label className="sv11-field-label" htmlFor={id}>{label} <span aria-hidden="true">*</span></label>
        {children}
        {hint && <small className="sv11-field-hint" id={`${id}-hint`}>{hint}</small>}
        {error && <small className="sv-register-field-error" id={`${id}-error`} role="alert">{error}</small>}
    </div>;
}

function SectionTitle({ number, title, description, id }) {
    return <div className="sv11-section-title">
        <div className="sv11-section-number" aria-hidden="true">{number}</div>
        <div><h2 id={id}>{title}</h2><p>{description}</p></div>
    </div>;
}

function PriceRow({ label, value }) {
    return <div className="sv11-price-row"><span>{label}</span><strong>{formatRegistrationRupiah(value)}</strong></div>;
}

export default function Register({ plans = [], configurations = [], selected = {} }) {
    const availablePlan = resolveRegistrationSelection(plans, selected?.plan, 'starter');
    const availableConfig = resolveRegistrationSelection(configurations, selected?.config, 'software-agency');
    const startingCycle = ['annual', 'monthly'].includes(selected?.billing) ? selected.billing : 'monthly';
    const form = useForm({
        business_name: '', name: '', email: '', phone: '', password: '', password_confirmation: '',
        plan_key: availablePlan?.key || '',
        configuration_key: availableConfig?.key || '',
        billing_cycle: startingCycle,
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);
    const plan = resolveRegistrationSelection(plans, form.data.plan_key, availablePlan?.key);
    const configuration = resolveRegistrationSelection(configurations, form.data.configuration_key, availableConfig?.key);
    const summary = registrationPriceSummary(plan, configuration, form.data.billing_cycle);
    const mismatch = registrationPasswordError(form.data.password, form.data.password_confirmation);
    const confirmationError = form.errors.password_confirmation || mismatch;
    const billingLabel = summary.annual ? 'tahun' : 'bulan';

    useEffect(() => {
        const first = FIELDS.find(field => form.errors[field]);
        if (first) {
            const input = document.getElementById(`sv11-${first}`);
            input?.focus({ preventScroll: true });
            input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [form.errors]);

    const change = key => event => {
        form.setData(key, event.target.value);
        if (form.errors[key]) form.clearErrors(key);
        if (key === 'password' || key === 'password_confirmation') form.clearErrors('password', 'password_confirmation');
    };

    const submit = event => {
        event.preventDefault();
        if (form.processing) return;
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

    const invalid = key => Boolean(form.errors[key] || (key === 'password_confirmation' && mismatch));
    const fieldProps = (key, extra = {}) => ({
        id: `sv11-${key}`, name: key, value: form.data[key], onChange: change(key),
        required: true, 'aria-invalid': invalid(key), ...extra,
    });

    return <PublicShell title="Buat Workspace Santovate CRM">
        <Head><meta name="robots" content="noindex,follow"/></Head>
        <div className="sv11-registration">
            <header className="sv11-hero">
                <div className="sv11-hero-copy">
                    <span className="sv11-kicker"><span className="sv11-kicker-dot"/> MULAI DENGAN SANTOVATE CRM</span>
                    <h1>Bangun workspace.<br/><em>Kelola penjualan lebih rapi.</em></h1>
                    <p>Siapkan akun bisnis Anda dalam beberapa langkah. Pilih paket dan alur kerja yang tepat, lalu aktifkan setelah pembayaran terverifikasi.</p>
                </div>
                <div className="sv11-hero-steps" aria-label="Tahapan aktivasi workspace">
                    <div className="sv11-step sv11-step--current"><span>1</span><strong>Isi informasi</strong><small>Data & pilihan paket</small></div>
                    <div className="sv11-step-line" aria-hidden="true"/>
                    <div className="sv11-step"><span>2</span><strong>Pembayaran</strong><small>Checkout iPaymu</small></div>
                    <div className="sv11-step-line" aria-hidden="true"/>
                    <div className="sv11-step"><span>3</span><strong>Mulai gunakan</strong><small>Aktif setelah verifikasi</small></div>
                </div>
            </header>

            <form className="sv-register-card sv11-register-card" onSubmit={submit}>
                <div className="sv11-main">
                    <section className="sv11-form-section" aria-labelledby="sv11-business-heading">
                        <SectionTitle id="sv11-business-heading" number="01" title="Informasi bisnis" description="Kenalkan bisnis Anda dan tentukan pengelola workspace."/>
                        <div className="sv11-grid">
                            <FormField id="sv11-business_name" label="Nama bisnis" hint="Nama perusahaan, toko, atau brand yang akan menggunakan CRM." error={form.errors.business_name}>
                                <input {...fieldProps('business_name', { autoComplete: 'organization', maxLength: 180, placeholder: 'Contoh: PT Haruka Indonesia' })}/>
                            </FormField>
                            <FormField id="sv11-name" label="Nama admin" hint="Nama penanggung jawab utama yang akan mengelola workspace." error={form.errors.name}>
                                <input {...fieldProps('name', { autoComplete: 'name', maxLength: 180, placeholder: 'Nama lengkap Anda' })}/>
                            </FormField>
                            <FormField id="sv11-email" label="Email login" hint="Dipakai untuk masuk ke CRM dan menerima informasi akun." error={form.errors.email}>
                                <input {...fieldProps('email', { type: 'email', autoComplete: 'email', maxLength: 255, placeholder: 'nama@bisnis.com' })}/>
                            </FormField>
                            <FormField id="sv11-phone" label="Nomor WhatsApp" hint="Gunakan nomor aktif, misalnya 081234567890." error={form.errors.phone}>
                                <input {...fieldProps('phone', { type: 'tel', inputMode: 'tel', autoComplete: 'tel', maxLength: 50, placeholder: '08xxxxxxxxxx' })}/>
                            </FormField>
                        </div>
                    </section>

                    <section className="sv11-form-section" aria-labelledby="sv11-plan-heading">
                        <SectionTitle id="sv11-plan-heading" number="02" title="Pilihan layanan" description="Sesuaikan kapasitas tim dan workflow dengan kebutuhan bisnis."/>
                        <div className="sv11-grid">
                            <FormField id="sv11-plan_key" label="Paket CRM" hint="Menentukan fitur dan kapasitas pengguna pada workspace." error={form.errors.plan_key}>
                                <select {...fieldProps('plan_key')}>{plans.map(item => <option key={item.key} value={item.key}>{item.name}</option>)}</select>
                            </FormField>
                            <FormField id="sv11-configuration_key" label="Jenis bisnis" hint="Mengatur template pipeline dan alur kerja awal sesuai industri." error={form.errors.configuration_key}>
                                <select {...fieldProps('configuration_key')}>{configurations.map(item => <option key={item.key} value={item.key}>{item.name}</option>)}</select>
                            </FormField>
                            <div className="sv11-field sv11-field--full">
                                <span className="sv11-field-label" id="sv11-cycle-title">Periode berlangganan <span aria-hidden="true">*</span></span>
                                <div className="sv11-cycle" role="group" aria-labelledby="sv11-cycle-title">
                                    <button type="button" className={!summary.annual ? 'is-selected' : ''} aria-pressed={!summary.annual} onClick={() => { form.setData('billing_cycle', 'monthly'); form.clearErrors('billing_cycle'); }}>Bulanan <small>Lebih fleksibel</small></button>
                                    <button type="button" className={summary.annual ? 'is-selected' : ''} aria-pressed={summary.annual} onClick={() => { form.setData('billing_cycle', 'annual'); form.clearErrors('billing_cycle'); }}>Tahunan <small>Komitmen jangka panjang</small></button>
                                </div>
                                {form.errors.billing_cycle && <small role="alert" className="sv-register-field-error">{form.errors.billing_cycle}</small>}
                            </div>
                        </div>
                    </section>

                    <section className="sv11-form-section sv11-form-section--last" aria-labelledby="sv11-password-heading">
                        <SectionTitle id="sv11-password-heading" number="03" title="Keamanan akun" description="Buat password untuk administrator workspace."/>
                        <div className="sv11-grid">
                            <FormField id="sv11-password" label="Password" hint="Minimal 8 karakter. Gunakan kombinasi yang sulit ditebak." error={form.errors.password}>
                                <div className="sv11-password-input"><input {...fieldProps('password', { type: showPassword ? 'text' : 'password', minLength: 8, autoComplete: 'new-password', placeholder: 'Minimal 8 karakter' })}/><button type="button" className="sv11-toggle-password" onClick={() => setShowPassword(value => !value)} aria-label={showPassword ? 'Sembunyikan password' : 'Lihat password'}>{showPassword ? 'Sembunyikan' : 'Lihat'}</button></div>
                            </FormField>
                            <FormField id="sv11-password_confirmation" label="Konfirmasi password" hint="Masukkan kembali password yang sama persis." error={confirmationError}>
                                <div className="sv11-password-input"><input {...fieldProps('password_confirmation', { type: showConfirmation ? 'text' : 'password', minLength: 8, autoComplete: 'new-password', placeholder: 'Ulangi password Anda' })}/><button type="button" className="sv11-toggle-password" onClick={() => setShowConfirmation(value => !value)} aria-label={showConfirmation ? 'Sembunyikan konfirmasi password' : 'Lihat konfirmasi password'}>{showConfirmation ? 'Sembunyikan' : 'Lihat'}</button></div>
                            </FormField>
                        </div>
                    </section>
                </div>

                <aside className="sv11-order-summary" aria-label="Ringkasan pendaftaran">
                    <div className="sv11-summary-top"><span>RINGKASAN WORKSPACE</span><span className="sv11-summary-icon" aria-hidden="true">S</span></div>
                    <h2>Paket pilihan Anda</h2>
                    <p>Semua rincian diperbarui saat Anda mengganti paket, jenis bisnis, atau periode berlangganan.</p>
                    <div className="sv11-chosen-plan"><span>PAKET CRM</span><strong>{plan?.name || 'Pilih paket'}</strong><small>{plan?.user_limit != null ? `Hingga ${Number(plan.user_limit).toLocaleString('id-ID')} pengguna sesuai paket` : 'Kapasitas mengikuti paket'}</small></div>
                    <div className="sv11-chosen-config"><span>Jenis bisnis</span><strong>{configuration?.name || 'Pilih jenis bisnis'}</strong></div>
                    <div className="sv11-price-breakdown">
                        <PriceRow label="Harga paket" value={summary.planAmount}/>
                        <PriceRow label="Konfigurasi bisnis" value={summary.configurationAmount}/>
                        {summary.annual && summary.annualSavings > 0 && <div className="sv11-saving">Potensi hemat {formatRegistrationRupiah(summary.annualSavings)} dibanding 12 bulan pembayaran bulanan</div>}
                    </div>
                    <div className="sv11-total"><span>Estimasi total / {billingLabel}</span><strong>{formatRegistrationRupiah(summary.total)}</strong><small>Nilai final dikonfirmasi pada halaman checkout.</small></div>
                    <div className="sv11-assurance"><span className="sv11-assurance-icon" aria-hidden="true">✓</span><div><strong>Aktif setelah pembayaran terverifikasi</strong><p>Pendaftaran membuat workspace berstatus menunggu pembayaran. Aktivasi dilakukan setelah konfirmasi dari gateway.</p></div></div>
                    <Link className="sv11-back-link" href="/pricing">← Lihat perbandingan paket</Link>
                </aside>

                <div className="sv11-submit-bar">
                    {Object.entries(form.errors).filter(([key]) => !FIELDS.includes(key)).map(([key, value]) => <div className="sv11-global-error" key={key} role="alert">{value}</div>)}
                    <div><strong>Siap membuat workspace?</strong><p>Anda akan melanjutkan ke checkout iPaymu. Tidak perlu membayar hingga Anda menyetujui instruksi pembayaran.</p></div>
                    <button className="sv11-submit" type="submit" disabled={form.processing || Boolean(mismatch) || !plan || !configuration}>
                        {form.processing ? 'Menyiapkan workspace…' : 'Buat workspace & lanjut ke pembayaran'} <span aria-hidden="true">→</span>
                    </button>
                </div>
            </form>
        </div>
    </PublicShell>;
}
