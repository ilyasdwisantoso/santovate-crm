// V9.3.5: Convert the EXACT merchant-provided QRIS payload to an inline SVG matrix.
// No external QR service, iframe or remote QR image is needed.
// QR algorithm: Kazuhiko Arase (MIT), via qrcode-terminal 0.12.0 (Apache-2.0); licenses in Vendor/QRCode.
import QRCode from '../Vendor/QRCode/index.js';
import ErrorLevel from '../Vendor/QRCode/QRErrorCorrectLevel.js';

export function localQrisMatrix(raw) {
    if (typeof raw !== 'string') return null;
    const payload = raw.trim();
    // QRIS follows EMVCo merchant-presented TLV and begins with tag 00 / 01 or 02.
    if (!/^00020[12]/.test(payload) || payload.length < 50 || payload.length > 3500) return null;
    try {
        const qr = new QRCode(-1, ErrorLevel.M);
        qr.addData(payload);
        qr.make();
        const size = qr.getModuleCount();
        const squares = [];
        for (let y = 0; y < size; y++) {
            for (let x = 0; x < size; x++) {
                if (qr.isDark(y, x)) squares.push(`M${x + 4} ${y + 4}h1v1h-1z`);
            }
        }
        return { size: size + 8, path: squares.join(''), payloadLength: payload.length };
    } catch {
        return null;
    }
}
