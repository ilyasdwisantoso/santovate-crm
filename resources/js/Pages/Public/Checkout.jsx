import { useCallback, useMemo, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import PublicShell from './PublicShell';
import usePaymentStream from '../../Hooks/usePaymentStream';

const rupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value || 0));

const planLabel = { starter: 'Essential', growth: 'Most popular', scale: 'Advanced' };
const methodIcon = { va: '🏦', qris: '▦', ewallet: '◉', cstore: '▤', cc: '◇', paylater: '◌' };

export default function Checkout({ subscription, plans = [], configurations = [], paymentChannels = [], gateway = {}, support }) {
    const selection = useForm({
        plan_key: subscription?.plan?.key || plans[0]?.key || 'starter',
        configuration_key: subscription?.business_configuration?.key || configurations[0]?.key || 'software-agency',
        billing_cycle: subscription?.billing_cycle || 'monthly',
    });

    const availableMethods = useMemo(() => paymentChannels
        .map((method) => ({
            ...method,
            channels: (method.channels || []).filter((channel) => channel.feature_status !== 'inactive' && channel.health_status !== 'offline'),
        }))
        .filter((method) => method.channels.length > 0), [paymentChannels]);

    const [method, setMethod] = useState(availableMethods[0]?.code || 'va');
    const currentMethod = availableMethods.find((item) => item.code === method) || availableMethods[0];
    const [channel, setChannel] = useState(currentMethod?.channels?.[0]?.code || 'bca');
    const [creatingPayment, setCreatingPayment] = useState(false);
    const [paymentError, setPaymentError] = useState('');
    const [session, setSession] = useState(null);
    const paymentWindow = useRef(null);

    const chooseMethod = (next) => {
        setMethod(next.code);
        setChannel(next.channels?.[0]?.code || '');
    };

    const updateSelection = (event) => {
        event.preventDefault();
        selection.patch('/subscription/checkout', { preserveScroll: true });
    };

    const paymentComplete = useCallback(() => {
        try { paymentWindow.current?.close(); } catch { /* noop */ }
        window.setTimeout(() => { window.location.href = '/dashboard'; }, 1400);
    }, []);

    const { payment, connection } = usePaymentStream({
        reference: session?.reference,
        streamUrl: session?.stream_url,
        statusUrl: session?.status_url,
        initialStatus: 'pending',
        onComplete: paymentComplete,
    });

    const startPayment = async () => {
        if (!subscription || !gateway.configured || !method || !channel || creatingPayment) return;
        setCreatingPayment(true);
        setPaymentError('');

        const popup = window.open('', 'ipaymu-secure-payment');
        paymentWindow.current = popup;
        if (popup) {
            popup.document.title = 'Connecting to iPaymu';
            popup.document.body.innerHTML = '<div style="font-family:system-ui;padding:40px;color:#111827">Menyiapkan halaman pembayaran aman…</div>';
        }

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch('/subscription/pay', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ payment_method: method, payment_channel: channel }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Tidak dapat membuat sesi pembayaran.');

            setSession(data);
            if (popup) popup.location.href = data.checkout_url;
            else window.location.href = data.checkout_url;
        } catch (error) {
            try { popup?.close(); } catch { /* noop */ }
            setPaymentError(error.message || 'Terjadi kesalahan saat menghubungkan iPaymu.');
        } finally {
            setCreatingPayment(false);
        }
    };

    const selectedPlan = plans.find((plan) => plan.key === selection.data.plan_key);
    const selectedConfiguration = configurations.find((item) => item.key === selection.data.configuration_key);
    const connectionLabel = connection === 'live' ? 'Realtime connected'
        : connection === 'reconnecting' ? 'Reconnecting…'
        : connection === 'complete' ? 'Payment verified'
        : session ? 'Connecting realtime…' : 'Ready';

    return <PublicShell title="Checkout Subscription">
        <div className="sv-checkout-v2">
            <section className="sv-checkout-hero-v2">
                <div>
                    <span className="sv-checkout-kicker">SECURE SUBSCRIPTION CHECKOUT</span>
                    <h1>Aktifkan workspace.<br/><em>Mulai jualan lebih rapi.</em></h1>
                    <p>Pilih paket, konfigurasi bisnis, lalu selesaikan pembayaran melalui iPaymu. Status pembayaran tersinkron otomatis ke workspace.</p>
                </div>
                <div className={`sv-gateway-pill ${gateway.mode === 'production' ? 'live' : 'sandbox'}`}>
                    <i/><span><b>iPaymu {gateway.mode === 'production' ? 'Live' : 'Sandbox'}</b><small>{gateway.realtime === 'sse' ? 'SSE realtime sync' : 'Secure gateway'}</small></span>
                </div>
            </section>

            {gateway.mode === 'sandbox' && <div className="sv-sandbox-banner">
                <span>TEST MODE</span>
                <p>Transaksi ini menggunakan iPaymu Sandbox. Tidak ada dana riil yang diproses.</p>
            </div>}

            <div className="sv-checkout-layout-v2">
                <main className="sv-checkout-main-v2">
                    <form className="sv-checkout-section-v2" onSubmit={updateSelection}>
                        <div className="sv-checkout-section-head"><span>01</span><div><h2>Pilih paket</h2><p>Subscription utama menentukan kapasitas dan fitur workspace.</p></div></div>
                        <div className="sv-plan-picker-v2">
                            {plans.map((plan) => <button key={plan.key} type="button" className={selection.data.plan_key === plan.key ? 'active' : ''} onClick={() => selection.setData('plan_key', plan.key)}>
                                <small>{planLabel[plan.key] || 'Plan'}</small><strong>{plan.name}</strong>
                                <b>{rupiah(selection.data.billing_cycle === 'annual' ? plan.annual_price : plan.monthly_price)}</b>
                                <span>{plan.user_limit} user · {Number(plan.prospect_limit || 0).toLocaleString('id-ID')} prospect</span>
                            </button>)}
                        </div>

                        <div className="sv-checkout-section-head compact"><span>02</span><div><h2>Konfigurasi bisnis</h2><p>Workflow dan terminology disesuaikan dengan industri client.</p></div></div>
                        <div className="sv-config-picker-v2">
                            {configurations.map((config) => <button type="button" key={config.key} className={selection.data.configuration_key === config.key ? 'active' : ''} onClick={() => selection.setData('configuration_key', config.key)}>
                                <i/><span><strong>{config.name}</strong><small>{config.industry}</small></span>
                            </button>)}
                        </div>

                        <div className="sv-billing-row-v2">
                            <div><strong>Billing cycle</strong><small>Pilih siklus pembayaran subscription.</small></div>
                            <div className="sv-segment-v2">
                                <button type="button" className={selection.data.billing_cycle === 'monthly' ? 'active' : ''} onClick={() => selection.setData('billing_cycle','monthly')}>Bulanan</button>
                                <button type="button" className={selection.data.billing_cycle === 'annual' ? 'active' : ''} onClick={() => selection.setData('billing_cycle','annual')}>Tahunan</button>
                            </div>
                            <button className="sv-apply-plan-v2" disabled={selection.processing}>{selection.processing ? 'Menyimpan…' : 'Terapkan konfigurasi'}</button>
                        </div>
                    </form>

                    <section className="sv-checkout-section-v2">
                        <div className="sv-checkout-section-head"><span>03</span><div><h2>Metode pembayaran</h2><p>Channel aktif diambil langsung dari environment iPaymu yang sedang digunakan.</p></div></div>
                        <div className="sv-method-picker-v2">
                            {availableMethods.map((item) => <button type="button" key={item.code} className={method === item.code ? 'active' : ''} onClick={() => chooseMethod(item)}>
                                <i>{methodIcon[item.code] || '◈'}</i><span><strong>{item.name}</strong><small>{item.description || item.code}</small></span>
                            </button>)}
                        </div>
                        {currentMethod && <div className="sv-channel-grid-v2">
                            {currentMethod.channels.map((item) => <button type="button" key={item.code} className={channel === item.code ? 'active' : ''} onClick={() => setChannel(item.code)}>
                                {item.logo ? <img src={item.logo} alt=""/> : <span>{item.name.slice(0,2).toUpperCase()}</span>}
                                <div><strong>{item.name}</strong><small>{item.health_status === 'online' ? 'Online' : item.health_status}</small></div><i/>
                            </button>)}
                        </div>}
                    </section>
                </main>

                <aside className="sv-checkout-summary-v2">
                    <div className="sv-summary-top-v2"><small>ORDER SUMMARY</small><h2>{subscription?.plan?.name || selectedPlan?.name || 'Subscription'}</h2><p>{subscription?.business_configuration?.name || selectedConfiguration?.name || 'Business configuration'}</p></div>
                    <div className="sv-summary-lines-v2">
                        <span><em>Base subscription</em><b>{rupiah(subscription?.base_amount)}</b></span>
                        <span><em>Business configuration</em><b>{rupiah(subscription?.configuration_amount)}</b></span>
                    </div>
                    <div className="sv-summary-total-v2"><span>Total <small>{subscription?.billing_cycle === 'annual' ? '/ tahun' : '/ bulan'}</small></span><strong>{rupiah(subscription?.total_amount)}</strong></div>

                    <button className="sv-pay-button-v2" type="button" onClick={startPayment} disabled={!subscription || !gateway.configured || creatingPayment || !!session}>
                        <span>{creatingPayment ? 'Menghubungkan iPaymu…' : session ? 'Pembayaran sedang dipantau' : 'Bayar aman via iPaymu'}</span><i>→</i>
                    </button>
                    {!gateway.configured && <p className="sv-payment-error-v2">Kredensial iPaymu untuk environment ini belum dikonfigurasi.</p>}
                    {paymentError && <p className="sv-payment-error-v2">{paymentError}</p>}

                    <div className={`sv-live-payment-v2 ${session ? 'visible' : ''} ${payment.active ? 'done' : ''}`}>
                        <div className="sv-live-orb-v2"><i/><i/><span>✓</span></div>
                        <div className="sv-live-copy-v2"><small>{connectionLabel}</small><strong>{payment.active ? 'Pembayaran terverifikasi' : 'Menunggu pembayaran'}</strong><p>{payment.active ? 'Workspace sedang diaktifkan dan Anda akan diarahkan ke dashboard.' : 'Selesaikan pembayaran pada halaman iPaymu yang terbuka. Status akan berubah otomatis.'}</p></div>
                        {session && <a href={session.checkout_url} target="_blank" rel="noreferrer">Buka halaman pembayaran ↗</a>}
                    </div>

                    <div className="sv-security-v2"><span>◆</span><p><b>Realtime & secure</b><small>Callback iPaymu divalidasi server-side. SSE hanya menampilkan status milik workspace Anda.</small></p></div>
                    <small className="sv-help-v2">Butuh bantuan? {support?.phone || '081293047587'}</small>
                </aside>
            </div>
        </div>
    </PublicShell>;
}
