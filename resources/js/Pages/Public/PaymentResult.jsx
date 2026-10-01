import { Link } from '@inertiajs/react';
import { useCallback } from 'react';
import PublicShell from './PublicShell';
import PaymentInstruction from '../../Components/PaymentInstruction';
import usePaymentStream from '../../Hooks/usePaymentStream';

const rupiah = (value) => new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(Number(value || 0));

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
    return <PublicShell title="Status Pembayaran">
        <div className="sv-payment-result-v2">
            <div className={`sv-result-visual-v2 ${paid ? 'done' : ''}`}><i/><i/><span>{paid ? '✓' : '↻'}</span></div>
            <span className={`sv-result-mode-v2 ${gateway.mode === 'production' ? 'live' : 'sandbox'}`}>iPaymu {gateway.mode === 'production' ? 'Live' : 'Sandbox'} · {connection === 'live' ? 'SSE connected' : connection}</span>
            <h1>{paid ? 'Pembayaran berhasil.' : 'Menunggu konfirmasi pembayaran.'}</h1>
            <p>{paid ? 'Subscription sudah terverifikasi. Workspace sedang dibuka.' : 'Selesaikan instruksi pembayaran di bawah ini. Jangan membuat pembayaran kedua untuk reference yang sama; status akan berubah otomatis setelah callback iPaymu diterima.'}</p>
            {!paid && <PaymentInstruction presentation={presentation}/>}
            <div className="sv-result-detail-v2">
                <span><small>Reference</small><strong>{reference || '—'}</strong></span>
                <span><small>Subscription</small><strong>{subscription?.plan?.name || '—'}</strong></span>
                <span><small>Total</small><strong>{rupiah(payment?.amount)}</strong></span>
                <span><small>Status</small><strong>{String(live.status || 'unknown').toUpperCase()}</strong></span>
            </div>
            {paid ? <Link href="/dashboard" className="sv-result-button-v2">Buka Dashboard →</Link> : <Link href="/subscription/checkout" className="sv-result-button-v2 secondary">Kembali ke Checkout</Link>}
        </div>
    </PublicShell>;
}
