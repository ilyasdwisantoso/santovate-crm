import { Link } from '@inertiajs/react';
import { useCallback } from 'react';
import PublicShell from './PublicShell';
import PaymentInstruction from '../../Components/PaymentInstruction';
import usePaymentStream from '../../Hooks/usePaymentStream';

const rupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const connectionCopy = (connection) => ({
    live: 'Realtime terhubung',
    reconnecting: 'Menghubungkan ulang…',
    complete: 'Pembayaran terverifikasi',
    idle: 'Menyiapkan realtime…',
}[connection] || 'Memantau status…');

export default function PaymentResult({ payment, subscription, presentation, gateway = {} }) {
    const reference = payment?.reference_id;
    const done = useCallback(() => window.setTimeout(() => { window.location.href = '/dashboard'; }, 1500), []);
    const { payment: live, connection } = usePaymentStream({
        reference,
        streamUrl: reference ? `/subscription/payment-stream?reference=${encodeURIComponent(reference)}` : null,
        statusUrl: reference ? `/subscription/status?reference=${encodeURIComponent(reference)}` : null,
        initialStatus: payment?.status || 'unknown',
        onComplete: done,
    });

    const paid = live.active || live.status === 'paid';
    const failed = ['failed', 'expired', 'cancelled', 'canceled'].includes(String(live.status || payment?.status || '').toLowerCase());

    return <PublicShell title="Status Pembayaran">
        <main className="payv3-page">
            <div className="payv3-ambient payv3-ambient-a"/><div className="payv3-ambient payv3-ambient-b"/>
            <section className={`payv3-shell ${paid ? 'is-paid' : ''} ${failed ? 'is-failed' : ''}`}>
                <header className="payv3-topbar">
                    <div className="payv3-brandmark"><span>S</span><div><strong>Santovate</strong><small>Secure checkout</small></div></div>
                    <div className={`payv3-env ${gateway.mode === 'production' ? 'live' : 'sandbox'}`}>
                        <i/><span>iPaymu {gateway.mode === 'production' ? 'Live' : 'Sandbox'}</span>
                    </div>
                </header>

                <div className="payv3-progress">
                    <div className="done"><i>✓</i><span><b>Order dibuat</b><small>Detail pembayaran siap</small></span></div>
                    <em/>
                    <div className={paid ? 'done' : 'active'}><i>{paid ? '✓' : '2'}</i><span><b>{paid ? 'Pembayaran diterima' : 'Menunggu pembayaran'}</b><small>{connectionCopy(connection)}</small></span></div>
                    <em/>
                    <div className={paid ? 'done' : ''}><i>{paid ? '✓' : '3'}</i><span><b>Workspace aktif</b><small>Entitlement otomatis</small></span></div>
                </div>

                <div className="payv3-grid">
                    <section className="payv3-main">
                        <div className="payv3-status-head">
                            <div className={`payv3-status-orb ${paid ? 'done' : failed ? 'failed' : ''}`}>
                                <div className="payv3-orbit"/><div className="payv3-orbit second"/>
                                <span>{paid ? '✓' : failed ? '!' : ''}</span>
                            </div>
                            <div>
                                <span className="payv3-status-label">{paid ? 'PAYMENT VERIFIED' : failed ? 'PAYMENT NEEDS ATTENTION' : 'PAYMENT IN PROGRESS'}</span>
                                <h1>{paid ? 'Pembayaran berhasil.' : failed ? 'Pembayaran tidak dapat dilanjutkan.' : 'Selesaikan pembayaran Anda.'}</h1>
                                <p>{paid
                                    ? 'Subscription sudah terverifikasi. Workspace sedang diaktifkan dan Anda akan diarahkan otomatis.'
                                    : failed
                                        ? 'Status transaksi sudah terminal. Kembali ke checkout untuk membuat pembayaran baru bila diperlukan.'
                                        : 'Ikuti instruksi di bawah. Status akan berubah otomatis tanpa refresh setelah callback iPaymu diterima.'}</p>
                            </div>
                        </div>

                        {!paid && !failed && <PaymentInstruction presentation={presentation}/>}
                        {paid && <div className="payv3-success-card"><i>✓</i><div><strong>Subscription aktif</strong><p>Semua entitlement paket sudah siap digunakan.</p></div></div>}
                    </section>

                    <aside className="payv3-summary">
                        <span className="payv3-kicker">ORDER SUMMARY</span>
                        <div className="payv3-plan">
                            <small>Subscription</small>
                            <strong>{subscription?.plan?.name || 'Santovate CRM'}</strong>
                            <span>{subscription?.business_configuration?.name || 'Business workspace'}</span>
                        </div>
                        <div className="payv3-total"><small>Total pembayaran</small><strong>{rupiah(payment?.amount)}</strong></div>
                        <dl>
                            <div><dt>Status</dt><dd className={paid ? 'success' : failed ? 'danger' : 'pending'}>{String(live.status || payment?.status || 'pending').toUpperCase()}</dd></div>
                            <div><dt>Reference</dt><dd>{reference || '—'}</dd></div>
                            <div><dt>Metode</dt><dd>{String(payment?.payment_method || '—').toUpperCase()} · {String(payment?.payment_channel || '—').toUpperCase()}</dd></div>
                            <div><dt>Koneksi</dt><dd><span className="payv3-live-dot"/> {connectionCopy(connection)}</dd></div>
                        </dl>
                        <div className="payv3-secure-note"><span>⌾</span><p><b>Pembayaran dipantau realtime</b><small>Callback divalidasi server-side dan status hanya dapat dilihat oleh workspace Anda.</small></p></div>
                        {paid
                            ? <Link href="/dashboard" className="payv3-primary-btn">Buka Dashboard <span>→</span></Link>
                            : <Link href="/subscription/checkout" className="payv3-secondary-btn">Kembali ke Checkout</Link>}
                    </aside>
                </div>
            </section>
        </main>
    </PublicShell>;
}
