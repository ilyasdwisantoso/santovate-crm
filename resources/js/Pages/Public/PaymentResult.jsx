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

function PaymentStatusIcon({ paid, failed }) {
    if (paid) {
        return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.5"/></svg>;
    }
    if (failed) {
        return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v6m0 4h.01"/><circle cx="12" cy="12" r="9"/></svg>;
    }
    return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M4 10h16M8 14h3"/></svg>;
}

export default function PaymentResult({ payment, subscription, presentation, gateway = {} }) {
    const reference = payment?.reference_id;

    // Keep the verified screen visible. The user can continue with the explicit CTA,
    // avoiding the previous 1.5s redirect that could visually cut the success state.
    const done = useCallback(() => {}, []);

    const { payment: live, connection } = usePaymentStream({
        reference,
        streamUrl: reference ? `/subscription/payment-stream?reference=${encodeURIComponent(reference)}` : null,
        statusUrl: reference ? `/subscription/status?reference=${encodeURIComponent(reference)}` : null,
        initialStatus: payment?.status || 'unknown',
        onComplete: done,
    });

    const currentStatus = String(live.status || payment?.status || 'pending').toLowerCase();
    const paid = live.active || currentStatus === 'paid';
    const failed = ['failed', 'expired', 'cancelled', 'canceled'].includes(currentStatus);
    const planName = subscription?.plan?.name || 'Santovate CRM';
    const configurationName = subscription?.business_configuration?.name || 'Business workspace';
    const methodLabel = `${String(payment?.payment_method || '—').toUpperCase()} · ${String(payment?.payment_channel || '—').toUpperCase()}`;

    return <PublicShell title="Status Pembayaran" wide>
        <div className="payv4-page payv5-page">
            <div className="payv4-ambient payv4-ambient-left" aria-hidden="true"/>
            <div className="payv4-ambient payv4-ambient-right" aria-hidden="true"/>

            <section className={`payv4-shell ${paid ? 'is-paid' : ''} ${failed ? 'is-failed' : ''}`}>
                <header className="payv4-topbar">
                    <div className="payv4-brandline">
                        <span className="payv4-brandmark">S</span>
                        <div className="payv4-brandcopy">
                            <strong>Santovate</strong>
                            <small>Secure checkout</small>
                        </div>
                        <div className="payv4-secure-caption">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <span>Transaksi Anda aman dan terenkripsi</span>
                        </div>
                    </div>

                    <div className={`payv4-env ${gateway.mode === 'production' ? 'live' : 'sandbox'}`}>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 2.8 8 7 10 4.2-2 7-5.3 7-10V6l-7-3Z"/><path d="m9.2 12 1.8 1.8 3.8-4"/></svg>
                        <span>Diproses melalui iPaymu {gateway.mode === 'production' ? 'Live' : 'Sandbox'}</span>
                    </div>
                </header>

                <div className="payv4-progress" aria-label="Progress pembayaran">
                    <div className="payv4-step is-done">
                        <i>✓</i><span><b>Order dibuat</b><small>Detail pembayaran siap</small></span>
                    </div>
                    <em className="is-done"/>
                    <div className={`payv4-step ${paid ? 'is-done' : failed ? 'is-failed' : 'is-active'}`}>
                        <i>{paid ? '✓' : failed ? '!' : '2'}</i>
                        <span><b>{paid ? 'Pembayaran diterima' : failed ? 'Pembayaran berhenti' : 'Menunggu pembayaran'}</b><small>{connectionCopy(connection)}</small></span>
                    </div>
                    <em className={paid ? 'is-done' : ''}/>
                    <div className={`payv4-step ${paid ? 'is-done' : ''}`}>
                        <i>{paid ? '✓' : '3'}</i><span><b>Workspace aktif</b><small>Entitlement otomatis</small></span>
                    </div>
                </div>

                <div className="payv4-grid">
                    <section className="payv4-main">
                        <div className="payv4-status-head">
                            <div className={`payv4-status-orb ${paid ? 'is-done' : failed ? 'is-failed' : ''}`}>
                                {!paid && !failed && <><span className="payv4-orbit one"/><span className="payv4-orbit two"/></>}
                                <span className="payv4-orb-core"><PaymentStatusIcon paid={paid} failed={failed}/></span>
                            </div>
                            <div className="payv4-status-copy">
                                <span className="payv4-eyebrow">{paid ? 'PAYMENT VERIFIED' : failed ? 'PAYMENT NEEDS ATTENTION' : 'PAYMENT IN PROGRESS'}</span>
                                <h1>{paid ? 'Pembayaran berhasil.' : failed ? 'Pembayaran tidak dapat dilanjutkan.' : 'Selesaikan pembayaran Anda'}</h1>
                                <p>{paid
                                    ? 'Pembayaran telah terverifikasi. Subscription aktif dan seluruh entitlement workspace sudah siap digunakan.'
                                    : failed
                                        ? 'Transaksi ini sudah berada pada status terminal. Kembali ke checkout apabila Anda perlu membuat pembayaran baru.'
                                        : 'Ikuti instruksi di bawah. Status akan berubah otomatis tanpa refresh setelah callback iPaymu diterima.'}</p>
                            </div>
                        </div>

                        {!paid && !failed && <PaymentInstruction presentation={presentation}/>}

                        {paid && <section className="payv4-success-panel">
                            <div className="payv4-success-hero">
                                <span className="payv4-success-icon">✓</span>
                                <div>
                                    <span className="payv4-eyebrow">WORKSPACE READY</span>
                                    <h2>Subscription Anda sudah aktif.</h2>
                                    <p>Tidak ada langkah pembayaran lain yang diperlukan. Anda dapat langsung melanjutkan ke dashboard Santovate CRM.</p>
                                </div>
                            </div>
                            <div className="payv4-success-grid">
                                <article><i>01</i><strong>Pembayaran terverifikasi</strong><small>Callback iPaymu sudah diterima dan tervalidasi.</small></article>
                                <article><i>02</i><strong>Subscription aktif</strong><small>{planName} · {configurationName}</small></article>
                                <article><i>03</i><strong>Entitlement siap</strong><small>Fitur dan kapasitas paket dapat digunakan sekarang.</small></article>
                            </div>
                            <Link href="/dashboard" className="payv4-success-cta">Buka Dashboard <span>→</span></Link>
                        </section>}

                        {failed && <section className="payv4-failed-panel">
                            <span>!</span>
                            <div><strong>Transaksi tidak lagi dapat diproses.</strong><p>Kembali ke checkout untuk memilih metode pembayaran dan membuat transaksi baru.</p></div>
                        </section>}
                    </section>

                    <aside className="payv4-summary">
                        <span className="payv4-eyebrow">ORDER SUMMARY</span>
                        <div className="payv4-plan">
                            <small>Subscription</small>
                            <strong>{planName}</strong>
                            <span>{configurationName}</span>
                        </div>

                        <div className="payv4-total">
                            <small>Total pembayaran</small>
                            <strong>{rupiah(payment?.amount)}</strong>
                        </div>

                        <dl className="payv4-meta">
                            <div><dt>Status</dt><dd><span className={`payv4-status-badge ${paid ? 'success' : failed ? 'danger' : 'pending'}`}>{paid ? 'PAID' : failed ? currentStatus.toUpperCase() : 'PENDING'}</span></dd></div>
                            <div><dt>Reference</dt><dd className="reference">{reference || '—'}</dd></div>
                            <div><dt>Metode</dt><dd>{methodLabel}</dd></div>
                            <div><dt>Koneksi</dt><dd><span className={`payv4-live-dot ${connection === 'live' || connection === 'complete' ? 'online' : ''}`}/> {connectionCopy(connection)}</dd></div>
                        </dl>

                        <div className="payv4-secure-note">
                            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 2.8 8 7 10 4.2-2 7-5.3 7-10V6l-7-3Z"/><path d="m9.2 12 1.8 1.8 3.8-4"/></svg></span>
                            <p><b>{paid ? 'Pembayaran sudah diverifikasi' : 'Pembayaran dipantau realtime'}</b><small>{paid ? 'Status subscription dan entitlement sudah disinkronkan.' : 'Callback divalidasi server-side dan status hanya dapat dilihat oleh workspace Anda.'}</small></p>
                        </div>

                        {paid
                            ? <Link href="/dashboard" className="payv4-primary-btn">Buka Dashboard <span>→</span></Link>
                            : <Link href="/subscription/checkout" className="payv4-secondary-btn"><span>←</span> Kembali ke Checkout</Link>}
                    </aside>
                </div>
            </section>
        </div>
    </PublicShell>;
}
