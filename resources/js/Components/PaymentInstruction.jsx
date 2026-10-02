import { useEffect, useMemo, useState } from 'react';

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

    return <button type="button" className="payv3-copy" onClick={copy}>
        <span>{copied ? 'Berhasil disalin' : label}</span>
        <i>{copied ? '✓' : '⧉'}</i>
    </button>;
}

export default function PaymentInstruction({ presentation }) {
    const qrSource = presentation?.qr_proxy_url || presentation?.qr_image || null;
    const countdown = useCountdown(presentation?.expired);
    const [qrError, setQrError] = useState(false);
    const [qrLoaded, setQrLoaded] = useState(false);

    useEffect(() => {
        setQrError(false);
        setQrLoaded(false);
    }, [qrSource]);

    if (!presentation || presentation.type === 'unknown') return null;

    const via = presentation.via || 'iPaymu';
    const channel = presentation.channel || '';
    const expiredLabel = formatExpiry(presentation.expired);

    return <section className="payv3-instruction">
        <header className="payv3-instruction-head">
            <div>
                <span className="payv3-kicker">PAYMENT INSTRUCTION</span>
                <h2>{via}{channel ? ` · ${channel}` : ''}</h2>
                <p>Instruksi dibuat oleh iPaymu. Santovate memantau status transaksi secara realtime.</p>
            </div>
            {presentation.expired && <div className={`payv3-expiry ${countdown === 'Kedaluwarsa' ? 'expired' : ''}`}>
                <small>Sisa waktu pembayaran</small>
                <strong>{countdown || '—'}</strong>
                <span>{expiredLabel}</span>
            </div>}
        </header>

        {presentation.type === 'qris' && <div className="payv3-qr-layout">
            <div className={`payv3-qr-frame ${qrLoaded ? 'is-loaded' : ''} ${qrError ? 'has-error' : 'is-loading'}`}>
                <div className="payv3-qr-corners" aria-hidden="true"><i/><i/><i/><i/></div>
                {!qrLoaded && !qrError && <div className="payv3-qr-skeleton" aria-hidden="true">
                    <span/><span/><span/><span/><span/><span/><span/><span/><span/>
                    <b>Menyiapkan QRIS…</b>
                </div>}
                {!qrError && qrSource
                    ? <img
                        src={qrSource}
                        alt="QRIS iPaymu"
                        className={qrLoaded ? 'visible' : ''}
                        onLoad={() => setQrLoaded(true)}
                        onError={() => setQrError(true)}
                    />
                    : <div className="payv3-qr-fallback">
                        <span>QR</span>
                        <strong>QR belum dapat dimuat</strong>
                        <small>Gunakan tombol fallback iPaymu di bawah untuk membuka QR asli.</small>
                    </div>}
                {!qrError && <div className="payv3-scanline" aria-hidden="true"/>}
            </div>

            <div className="payv3-instruction-copy">
                <span className="payv3-method-pill">QRIS · SECURE PAYMENT</span>
                <h3>Scan QR untuk menyelesaikan pembayaran</h3>
                <p>Buka mobile banking atau e-wallet yang mendukung QRIS. Anda tidak perlu refresh halaman ini setelah pembayaran.</p>
                <div className="payv3-trust-row"><span>✓ Status realtime</span><span>✓ Signature divalidasi server</span><span>✓ Tenant-scoped</span></div>
                {qrError && <div className="payv3-inline-error">QR proxy belum dapat mengambil image dari Sandbox. Transaksi tetap valid dan dapat dilanjutkan melalui iPaymu.</div>}
                {presentation.qr_template && <a className="payv3-secondary-link" href={presentation.qr_template} target="_blank" rel="noreferrer">Buka QR langsung di iPaymu <span>↗</span></a>}
            </div>
        </div>}

        {presentation.type === 'payment_code' && <div className="payv3-code-layout">
            <div>
                <span className="payv3-method-pill">{String(via || 'Payment code').toUpperCase()}</span>
                <h3>Gunakan nomor pembayaran berikut</h3>
                <p>Selesaikan transaksi melalui channel yang Anda pilih. Nomor ini hanya berlaku untuk reference pembayaran saat ini.</p>
            </div>
            <div className="payv3-code-box">
                <small>Nomor / kode pembayaran</small>
                <strong>{presentation.payment_no || '—'}</strong>
                <CopyButton value={presentation.payment_no}/>
            </div>
        </div>}

        {presentation.type === 'redirect' && <div className="payv3-redirect">
            <div className="payv3-redirect-icon">↗</div>
            <div>
                <span className="payv3-method-pill">SECURE REDIRECT</span>
                <h3>Lanjutkan pembayaran di iPaymu</h3>
                <p>Metode yang dipilih menggunakan halaman pembayaran aman milik iPaymu.</p>
            </div>
            <a href={presentation.checkout_url} target="_blank" rel="noreferrer">Buka pembayaran <span>→</span></a>
        </div>}
    </section>;
}
