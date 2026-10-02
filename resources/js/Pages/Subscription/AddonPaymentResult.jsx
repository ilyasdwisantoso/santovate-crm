import { Link } from '@inertiajs/react';
import { useCallback } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PaymentInstruction from '../../Components/PaymentInstruction';
import usePaymentStream from '../../Hooks/usePaymentStream';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const connectionCopy = (connection) => connection === 'live'
    ? 'Realtime terhubung'
    : connection === 'complete'
        ? 'Terverifikasi'
        : connection === 'reconnecting'
            ? 'Menghubungkan ulang…'
            : 'Memantau status…';

export default function AddonPaymentResult({ payment, order, presentation, gateway = {} }) {
    const ref = payment?.reference_id;
    const done = useCallback(() => window.setTimeout(() => { window.location.href = '/subscription/addons'; }, 1200), []);
    const { payment: live, connection } = usePaymentStream({
        reference: ref,
        streamUrl: ref ? `/subscription/addons/payment-stream?reference=${encodeURIComponent(ref)}` : null,
        statusUrl: ref ? `/subscription/addons/status?reference=${encodeURIComponent(ref)}` : null,
        initialStatus: payment?.status || 'unknown',
        onComplete: done,
    });

    const active = live.active || order?.status === 'activated';
    const failed = ['failed', 'expired', 'cancelled', 'canceled', 'pending_verification'].includes(String(live.status || payment?.status || '').toLowerCase());

    return <AppLayout title="Status Add-on" subtitle="Pembayaran aman dan aktivasi entitlement realtime.">
        <div className="payv3-page payv3-page-inapp">
            <section className={`payv3-shell ${active ? 'is-paid' : ''} ${failed ? 'is-failed' : ''}`}>
                <header className="payv3-topbar">
                    <div className="payv3-brandmark"><span>S</span><div><strong>Santovate</strong><small>Subscription add-on</small></div></div>
                    <div className={`payv3-env ${gateway.mode === 'production' ? 'live' : 'sandbox'}`}><i/><span>iPaymu {gateway.mode}</span></div>
                </header>

                <div className="payv3-grid">
                    <section className="payv3-main">
                        <div className="payv3-status-head">
                            <div className={`payv3-status-orb ${active ? 'done' : failed ? 'failed' : ''}`}>
                                <div className="payv3-orbit"/><div className="payv3-orbit second"/><span>{active ? '✓' : failed ? '!' : ''}</span>
                            </div>
                            <div>
                                <span className="payv3-status-label">{active ? 'ADD-ON ACTIVE' : failed ? 'PAYMENT REVIEW' : 'WAITING FOR PAYMENT'}</span>
                                <h1>{active ? 'Kapasitas berhasil ditambahkan.' : failed ? 'Pembayaran perlu diperiksa.' : 'Selesaikan pembayaran add-on.'}</h1>
                                <p>{active
                                    ? 'Entitlement workspace sudah diperbarui otomatis.'
                                    : 'Status add-on dipantau realtime. Jangan membuat pembayaran kedua untuk reference yang sama.'}</p>
                            </div>
                        </div>
                        {!active && !failed && <PaymentInstruction presentation={presentation}/>}
                    </section>

                    <aside className="payv3-summary">
                        <span className="payv3-kicker">ADD-ON SUMMARY</span>
                        <div className="payv3-plan"><small>Add-on</small><strong>{order?.addon?.name || 'Subscription Add-on'}</strong><span>+{Number(order?.resource_quantity || 0).toLocaleString('id-ID')} {order?.resource_key || 'capacity'}</span></div>
                        <div className="payv3-total"><small>Total pembayaran</small><strong>{money(payment?.amount)}</strong></div>
                        <dl>
                            <div><dt>Status</dt><dd className={active ? 'success' : failed ? 'danger' : 'pending'}>{String(live.status || payment?.status || 'pending').toUpperCase()}</dd></div>
                            <div><dt>Reference</dt><dd>{ref || '—'}</dd></div>
                            <div><dt>Koneksi</dt><dd><span className="payv3-live-dot"/> {connectionCopy(connection)}</dd></div>
                            <div><dt>Berakhir</dt><dd>{order?.ends_at ? new Date(order.ends_at).toLocaleDateString('id-ID') : '—'}</dd></div>
                        </dl>
                        {payment?.failure_reason && <p className="payv3-inline-error">{payment.failure_reason}</p>}
                        <Link href="/subscription/addons" className={active ? 'payv3-primary-btn' : 'payv3-secondary-btn'}>{active ? 'Lihat Add-ons →' : 'Kembali ke Add-ons'}</Link>
                    </aside>
                </div>
            </section>
        </div>
    </AppLayout>;
}
