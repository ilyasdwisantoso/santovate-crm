import { useEffect, useMemo, useState } from 'react';
import { qrImageCandidates, paymentTemplateUrl } from '../Utils/paymentQrSources';
import { localQrisMatrix } from '../Utils/ipaymuQrisLocal';

const formatExpiry = (value) => {
    if (!value) return null;
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const useCountdown = (value) => {
    const target = useMemo(() => {
        if (!value) return null;
        const parsed = new Date(String(value).replace(' ', 'T')).getTime();
        return Number.isNaN(parsed) ? null : parsed;
    }, [value]);

    const [now, setNow] = useState(Date.now());

    useEffect(() => {
        if (!target) return undefined;
        setNow(Date.now());
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, [target]);

    if (!target) return null;
    const remaining = Math.max(0, target - now);
    const hours = Math.floor(remaining / 3600000);
    const minutes = Math.floor((remaining % 3600000) / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);

    return remaining > 0
        ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
        : 'Kedaluwarsa';
};

function CopyButton({ value, label = 'Salin nomor' }) {
    const [copied, setCopied] = useState(false);
    if (!value) return null;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(String(value));
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1600);
        } catch {
            setCopied(false);
        }
    };

    return <button type="button" className="payv4-copy" onClick={copy}>
        <span>{copied ? 'Berhasil disalin' : label}</span>
        <i>{copied ? '✓' : '⧉'}</i>
    </button>;
}

export default function PaymentInstruction({ presentation }) {
    const countdown = useCountdown(presentation?.expired);
    const isLocalBrowser = typeof window !== 'undefined' && ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const candidates = qrImageCandidates(presentation, isLocalBrowser);
    const templateUrl = paymentTemplateUrl(presentation);
    // Draw QR directly from the merchant-provided QrString, without any external renderer.
    const localQr = useMemo(() => localQrisMatrix(presentation?.qr_string), [presentation?.qr_string]);
    const [candidateIndex, setCandidateIndex] = useState(0);
    const [qrLoaded, setQrLoaded] = useState(false);
    const showLocalQr = Boolean(localQr) && candidateIndex === 0;
    const imageIndex = localQr ? candidateIndex - 1 : candidateIndex;
    const qrSource = imageIndex >= 0 ? candidates[imageIndex] || null : null;
    const qrUnavailable = !showLocalQr && !qrSource;
    const qrReady = showLocalQr || qrLoaded;

    useEffect(() => {
        setCandidateIndex(0);
        setQrLoaded(false);
    }, [presentation?.qr_image, presentation?.qr_template, presentation?.qr_proxy_url, presentation?.qr_string]);

    if (!presentation || presentation.type === 'unknown') return null;

    const via = presentation.via || 'iPaymu';
    const channel = presentation.channel || '';
    const expiredLabel = formatExpiry(presentation.expired);

    return <section className="payv4-instruction">
        <header className="payv4-instruction-head">
            <div>
                <span className="payv4-eyebrow">PAYMENT INSTRUCTION</span>
                <h2>{via}{channel ? ` · ${channel}` : ''}</h2>
                <p>Instruksi dibuat oleh iPaymu. Santovate memantau status transaksi secara real-time.</p>
            </div>
            {presentation.expired && <div className={`payv4-expiry ${countdown === 'Kedaluwarsa' ? 'is-expired' : ''}`}>
                <small>Sisa waktu pembayaran</small>
                <strong>{countdown || '—'}</strong>
                <span>{expiredLabel}</span>
            </div>}
        </header>

        {presentation.type === 'qris' && <div className="payv4-qr-layout">
            <div className={`payv4-qr-frame ${qrReady ? 'is-loaded' : ''} ${qrUnavailable ? 'has-error' : 'is-loading'}`}>
                <div className="payv4-qr-corners" aria-hidden="true"><i/><i/><i/><i/></div>
                {!qrReady && !qrUnavailable && <div className="payv4-qr-skeleton" aria-hidden="true">
                    <span/><span/><span/><span/><span/><span/><span/><span/><span/>
                    <b>Menyiapkan QRIS…</b>
                </div>}
                {showLocalQr
                    ? <svg
                        className="payv935-local-qr"
                        role="img"
                        aria-label="QRIS dari kode pembayaran resmi iPaymu"
                        viewBox={`0 0 ${localQr.size} ${localQr.size}`}
                        xmlns="http://www.w3.org/2000/svg"
                        shapeRendering="crispEdges"
                    >
                        <rect width={localQr.size} height={localQr.size} fill="#ffffff"/>
                        <path d={localQr.path} fill="#111827"/>
                    </svg>
                    : qrSource
                        ? <img
                            src={qrSource}
                            alt="QRIS resmi iPaymu"
                            className={qrLoaded ? 'visible' : ''}
                            onLoad={() => setQrLoaded(true)}
                            onError={() => { setQrLoaded(false); setCandidateIndex((index) => index + 1); }}
                        />
                        : <div className="payv4-qr-fallback">
                            <span>QR</span>
                            <strong>QR belum dapat dimuat</strong>
                            <small>Buka QRIS dari halaman resmi iPaymu melalui tautan di sebelah.</small>
                        </div>}
                {!qrReady && !qrUnavailable && <div className="payv4-scanline" aria-hidden="true"/>}
            </div>

            <div className="payv4-instruction-copy">
                <span className="payv4-method-pill">QRIS · SECURE PAYMENT</span>
                <h3>Scan QR untuk menyelesaikan pembayaran</h3>
                <p>Buka mobile banking atau e-wallet yang mendukung QRIS. Anda tidak perlu refresh halaman ini setelah pembayaran.</p>
                <div className="payv4-trust-row">
                    <span><i>✓</i> Status realtime</span>
                    <span><i>✓</i> Signature divalidasi</span>
                    <span><i>✓</i> Tenant-scoped</span>
                </div>
                {showLocalQr && <p className="payv935-qr-caption">QRIS ditampilkan langsung dari kode pembayaran yang diberikan iPaymu. Tidak menggunakan layanan QR pihak ketiga.</p>}
                {qrUnavailable && <div className="payv4-inline-error">
                    <strong>QRIS belum dapat ditampilkan melalui browser.</strong>
                    <span>Gunakan halaman QR resmi iPaymu di bawah. Jangan membuat transaksi ganda.</span>
                </div>}
                {templateUrl && <a className="payv4-secondary-link" href={templateUrl} target="_blank" rel="noreferrer">Buka QR langsung di iPaymu <span>↗</span></a>}
            </div>
        </div>}

        {presentation.type === 'payment_code' && <div className="payv4-code-layout">
            <div>
                <span className="payv4-method-pill">{String(via || 'Payment code').toUpperCase()}</span>
                <h3>Gunakan nomor pembayaran berikut</h3>
                <p>Selesaikan transaksi melalui channel yang Anda pilih. Nomor ini hanya berlaku untuk reference pembayaran saat ini.</p>
            </div>
            <div className="payv4-code-box">
                <small>Nomor / kode pembayaran</small>
                <strong>{presentation.payment_no || '—'}</strong>
                <CopyButton value={presentation.payment_no}/>
            </div>
        </div>}

        {presentation.type === 'redirect' && <div className="payv4-redirect">
            <div className="payv4-redirect-icon">↗</div>
            <div>
                <span className="payv4-method-pill">SECURE REDIRECT</span>
                <h3>Lanjutkan pembayaran di iPaymu</h3>
                <p>Metode yang dipilih menggunakan halaman pembayaran aman milik iPaymu.</p>
            </div>
            <a href={presentation.checkout_url} target="_blank" rel="noreferrer">Buka pembayaran <span>→</span></a>
        </div>}
    </section>;
}
