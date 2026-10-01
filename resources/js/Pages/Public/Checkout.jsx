import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import PublicShell from './PublicShell';
import usePaymentStream from '../../Hooks/usePaymentStream';
import csrfFetch from '../../Utils/csrfFetch';

const rupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const planLabel = { starter: 'Essential', growth: 'Most popular', scale: 'Advanced' };
const methodIcon = { va: '🏦', qris: '◆', ewallet: '◈', cstore: '▤', cc: '◇', paylater: '◌' };

export default function Checkout({ subscription, plans = [], configurations = [], paymentChannels = [], gateway = {}, support }) {
    const initialSelection = useMemo(() => ({
        plan_key: subscription?.plan?.key || plans[0]?.key || 'starter',
        configuration_key: subscription?.business_configuration?.key || configurations[0]?.key || 'software-agency',
        billing_cycle: subscription?.billing_cycle || 'monthly',
    }), [subscription?.id]);

    const [selection, setSelection] = useState(initialSelection);
    const [syncState, setSyncState] = useState('saved');
    const [syncError, setSyncError] = useState('');
    const syncVersion = useRef(0);

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

    useEffect(() => {
        setSelection(initialSelection);
    }, [initialSelection.plan_key, initialSelection.configuration_key, initialSelection.billing_cycle]);

    useEffect(() => {
        const first = availableMethods[0];
        if (!first) return;
        if (!availableMethods.some((item) => item.code === method)) {
            setMethod(first.code);
            setChannel(first.channels?.[0]?.code || '');
        }
    }, [availableMethods, method]);

    useEffect(() => {
        const selectedMethod = availableMethods.find((item) => item.code === method);
        if (!selectedMethod) return;
        if (!selectedMethod.channels.some((item) => item.code === channel)) {
            setChannel(selectedMethod.channels?.[0]?.code || '');
        }
    }, [method, channel, availableMethods]);

    const selectedPlan = useMemo(
        () => plans.find((plan) => plan.key === selection.plan_key) || plans[0],
        [plans, selection.plan_key],
    );

    const selectedConfiguration = useMemo(
        () => configurations.find((item) => item.key === selection.configuration_key) || configurations[0],
        [configurations, selection.configuration_key],
    );

    const amounts = useMemo(() => {
        const annual = selection.billing_cycle === 'annual';
        const base = Number(annual ? selectedPlan?.annual_price : selectedPlan?.monthly_price) || 0;
        const configuration = Number(annual ? selectedConfiguration?.annual_addon_price : selectedConfiguration?.monthly_addon_price) || 0;
        return { base, configuration, total: base + configuration };
    }, [selection.billing_cycle, selectedPlan, selectedConfiguration]);

    const persistSelection = useCallback(async (nextSelection = selection) => {
        const version = ++syncVersion.current;
        setSyncState('saving');
        setSyncError('');

        const response = await csrfFetch('/subscription/checkout', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(nextSelection),
        });

        let data = {};
        try { data = await response.json(); } catch { data = {}; }

        if (!response.ok) {
            throw new Error(data.message || Object.values(data.errors || {})?.[0]?.[0] || 'Pilihan subscription tidak dapat disimpan.');
        }

        if (version === syncVersion.current) {
            setSyncState('saved');
            setSyncError('');
        }
        return data;
    }, [selection]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            persistSelection(selection).catch((error) => {
                setSyncState('error');
                setSyncError(error.message || 'Auto-save gagal.');
            });
        }, 320);

        return () => window.clearTimeout(timer);
    }, [selection.plan_key, selection.configuration_key, selection.billing_cycle]);

    const chooseMethod = (next) => {
        setMethod(next.code);
        setChannel(next.channels?.[0]?.code || '');
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
        if (!gateway.configured || !method || !channel || creatingPayment) return;
        setCreatingPayment(true);
        setPaymentError('');

        const popup = window.open('', 'ipaymu-secure-payment');
        paymentWindow.current = popup;
        if (popup) {
            popup.document.title = 'Connecting to iPaymu';
            popup.document.body.innerHTML = '<div style="font-family:system-ui;padding:40px;color:#111827">Menyiapkan halaman pembayaran aman…</div>';
        }

        try {
            // Always persist the latest instant UI selection before creating the iPaymu transaction.
            await persistSelection(selection);

            const response = await csrfFetch('/subscription/pay', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ payment_method: method, payment_channel: channel }),
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Tidak dapat membuat sesi pembayaran.');

            setSession(data);
            if (data.checkout_url) {
                if (popup) popup.location.href = data.checkout_url;
                else window.location.href = data.checkout_url;
            } else {
                try { popup?.close(); } catch { /* noop */ }
                window.location.href = data.result_url;
            }
        } catch (error) {
            try { popup?.close(); } catch { /* noop */ }
            setPaymentError(error.message || 'Terjadi kesalahan saat menghubungkan iPaymu.');
        } finally {
            setCreatingPayment(false);
        }
    };

    const connectionLabel = connection === 'live' ? 'Realtime connected'
        : connection === 'reconnecting' ? 'Reconnecting…'
        : connection === 'complete' ? 'Payment verified'
        : session ? 'Connecting realtime…' : 'Ready';

    const locked = Boolean(session && !payment.active);
    const setSelectionField = (key, value) => {
        if (locked) return;
        setSelection((current) => ({ ...current, [key]: value }));
    };

    return <PublicShell title="Checkout Subscription">
        <div className="sv-checkout-v2">
            <section className="sv-checkout-hero-v2">
                <div>
                    <span className="sv-checkout-kicker">SECURE SUBSCRIPTION CHECKOUT</span>
                    <h1>Aktifkan workspace.<br/><em>Mulai jualan lebih rapi.</em></h1>
                    <p>Pilih paket, konfigurasi bisnis, lalu selesaikan pembayaran melalui iPaymu. Total berubah realtime dan pilihan tersimpan otomatis.</p>
                </div>
                <div className={`sv-gateway-pill ${gateway.mode === 'production' ? 'live' : 'sandbox'}`}>
                    <i/><span><b>iPaymu {gateway.mode === 'production' ? 'Live' : 'Sandbox'}</b><small>{gateway.realtime === 'sse' ? 'SSE realtime sync' : 'Secure gateway'}</small></span>
                </div>
            </section>

            {gateway.mode === 'sandbox' && <div className="sv-sandbox-banner">
                <span>TEST MODE</span><p>Transaksi ini menggunakan iPaymu Sandbox. Tidak ada dana riil yang diproses.</p>
            </div>}

            <div className="sv-checkout-layout-v2">
                <main className="sv-checkout-main-v2">
                    <section className="sv-checkout-section-v2">
                        <div className="sv-checkout-section-head"><span>01</span><div><h2>Pilih paket</h2><p>Klik paket untuk melihat total baru secara realtime.</p></div></div>
                        <div className="sv-plan-picker-v2">
                            {plans.map((plan) => <button key={plan.key} type="button" disabled={locked} className={selection.plan_key === plan.key ? 'active' : ''} onClick={() => setSelectionField('plan_key', plan.key)}>
                                <small>{planLabel[plan.key] || 'Plan'}</small><strong>{plan.name}</strong>
                                <b>{rupiah(selection.billing_cycle === 'annual' ? plan.annual_price : plan.monthly_price)}</b>
                                <span>{plan.user_limit} user · {Number(plan.prospect_limit || 0).toLocaleString('id-ID')} prospect</span>
                            </button>)}
                        </div>

                        <div className="sv-checkout-section-head compact"><span>02</span><div><h2>Konfigurasi bisnis</h2><p>Tambahan workflow industri langsung masuk ke kalkulasi.</p></div></div>
                        <div className="sv-config-picker-v2">
                            {configurations.map((config) => <button type="button" disabled={locked} key={config.key} className={selection.configuration_key === config.key ? 'active' : ''} onClick={() => setSelectionField('configuration_key', config.key)}>
                                <i/><span><strong>{config.name}</strong><small>{config.industry}</small></span>
                            </button>)}
                        </div>

                        <div className="sv-billing-row-v2">
                            <div><strong>Billing cycle</strong><small>Ubah siklus dan total langsung diperbarui.</small></div>
                            <div className="sv-segment-v2">
                                <button type="button" disabled={locked} className={selection.billing_cycle === 'monthly' ? 'active' : ''} onClick={() => setSelectionField('billing_cycle', 'monthly')}>Bulanan</button>
                                <button type="button" disabled={locked} className={selection.billing_cycle === 'annual' ? 'active' : ''} onClick={() => setSelectionField('billing_cycle', 'annual')}>Tahunan</button>
                            </div>
                            <div className={`sv-autosave-state-v21 ${syncState}`}>
                                <i/>
                                <span>{syncState === 'saving' ? 'Menyimpan otomatis…' : syncState === 'error' ? 'Auto-save gagal' : 'Tersimpan otomatis'}</span>
                            </div>
                        </div>
                        {syncError && <p className="sv-inline-sync-error-v21">{syncError}</p>}
                    </section>

                    <section className="sv-checkout-section-v2">
                        <div className="sv-checkout-section-head"><span>03</span><div><h2>Metode pembayaran</h2><p>Channel aktif diambil dari environment iPaymu yang sedang digunakan.</p></div></div>
                        <div className="sv-method-picker-v2">
                            {availableMethods.map((item) => <button type="button" key={item.code} className={method === item.code ? 'active' : ''} onClick={() => chooseMethod(item)}>
                                <i>{methodIcon[item.code] || '◇'}</i><span><strong>{item.name}</strong><small>{item.description || item.code}</small></span>
                            </button>)}
                        </div>
                        {currentMethod && <div className="sv-channel-grid-v2">
                            {currentMethod.channels.map((item) => <button type="button" key={item.code} className={channel === item.code ? 'active' : ''} onClick={() => setChannel(item.code)}>
                                {item.logo ? <img src={item.logo} alt=""/> : <span>{item.name.slice(0, 2).toUpperCase()}</span>}
                                <div><strong>{item.name}</strong><small>{item.health_status === 'online' ? 'Online' : item.health_status}</small></div><i/>
                            </button>)}
                        </div>}
                    </section>
                </main>

                <aside className="sv-checkout-summary-v2">
                    <div className="sv-summary-top-v2"><small>ORDER SUMMARY</small><h2>{selectedPlan?.name || 'Subscription'}</h2><p>{selectedConfiguration?.name || 'Business configuration'}</p></div>
                    <div className={`sv-summary-lines-v2 ${syncState === 'saving' ? 'is-updating' : ''}`}>
                        <span><em>Base subscription</em><b>{rupiah(amounts.base)}</b></span>
                        <span><em>Business configuration</em><b>{rupiah(amounts.configuration)}</b></span>
                    </div>
                    <div className={`sv-summary-total-v2 ${syncState === 'saving' ? 'is-updating' : ''}`}>
                        <span>Total <small>{selection.billing_cycle === 'annual' ? '/ tahun' : '/ bulan'}</small></span>
                        <strong key={`${selection.plan_key}-${selection.configuration_key}-${selection.billing_cycle}`}>{rupiah(amounts.total)}</strong>
                    </div>

                    <button className="sv-pay-button-v2" type="button" onClick={startPayment} disabled={!gateway.configured || creatingPayment || !method || !channel || locked}>
                        <span>{creatingPayment ? 'Menghubungkan iPaymu…' : session ? 'Pembayaran sedang dipantau' : 'Bayar aman via iPaymu'}</span><i>→</i>
                    </button>
                    {!gateway.configured && <p className="sv-payment-error-v2">Kredensial iPaymu untuk environment ini belum dikonfigurasi.</p>}
                    {paymentError && <p className="sv-payment-error-v2">{paymentError}</p>}

                    <div className={`sv-live-payment-v2 ${session ? 'visible' : ''} ${payment.active ? 'done' : ''}`}>
                        <div className="sv-live-orb-v2"><i/><i/><span>✓</span></div>
                        <div className="sv-live-copy-v2"><small>{connectionLabel}</small><strong>{payment.active ? 'Pembayaran terverifikasi' : 'Menunggu pembayaran'}</strong><p>{payment.active ? 'Workspace sedang diaktifkan dan Anda akan diarahkan ke dashboard.' : 'Selesaikan pembayaran pada halaman iPaymu yang terbuka. Status akan berubah otomatis.'}</p></div>
                        {session && <a href={session.checkout_url} target="_blank" rel="noreferrer">Buka halaman pembayaran ↗</a>}
                    </div>

                    <div className="sv-security-v2"><span>◇</span><p><b>Realtime & secure</b><small>Callback iPaymu divalidasi server-side. SSE hanya menampilkan status milik workspace Anda.</small></p></div>
                    <small className="sv-help-v2">Butuh bantuan? {support?.phone || '081293047587'}</small>
                </aside>
            </div>
        </div>
    </PublicShell>;
}
