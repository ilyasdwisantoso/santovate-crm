export default function PaymentInstruction({ presentation }) {
    if (!presentation || presentation.type === 'unknown') return null;

    const via = presentation.via || 'iPaymu';
    const channel = presentation.channel || '';
    const expired = presentation.expired
        ? new Date(String(presentation.expired).replace(' ', 'T')).toLocaleString('id-ID')
        : null;

    return <section className="ipaymu-instruction">
        <div className="ipaymu-instruction-head">
            <div>
                <span>PAYMENT INSTRUCTION</span>
                <h3>{via}{channel ? ` · ${channel}` : ''}</h3>
            </div>
            {expired && <small>Berlaku sampai {expired}</small>}
        </div>

        {presentation.type === 'qris' && <div className="ipaymu-qr">
            {presentation.qr_image
                ? <img src={presentation.qr_image} alt="QRIS iPaymu"/>
                : presentation.qr_string
                    ? <pre>{presentation.qr_string}</pre>
                    : null}
            <div>
                <strong>Scan QR untuk membayar</strong>
                <p>Gunakan aplikasi pembayaran yang mendukung QRIS. Status di halaman ini diperbarui otomatis setelah callback iPaymu diterima.</p>
                {presentation.qr_template && <a href={presentation.qr_template} target="_blank" rel="noreferrer">Buka QR di iPaymu ↗</a>}
            </div>
        </div>}

        {presentation.type === 'payment_code' && <div className="ipaymu-code">
            <small>Nomor / kode pembayaran</small>
            <strong>{presentation.payment_no || '—'}</strong>
            <p>Selesaikan pembayaran melalui channel yang dipilih. Jangan membuat transaksi kedua untuk reference yang sama.</p>
        </div>}

        {presentation.type === 'redirect' && <div className="ipaymu-redirect">
            <strong>Lanjutkan pembayaran di iPaymu</strong>
            <p>Metode ini menggunakan halaman pembayaran iPaymu.</p>
            <a href={presentation.checkout_url} target="_blank" rel="noreferrer">Buka halaman pembayaran ↗</a>
        </div>}
    </section>;
}
