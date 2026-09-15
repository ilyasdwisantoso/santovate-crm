import { useState } from 'react';
import Icon from './Icon';

const options = {
    logistics: 'Halo Santovate, saya ingin demo Santovate CRM untuk bisnis Logistics & Freight. Mohon jelaskan paket, workflow pipeline, dan integrasi WhatsApp yang tersedia.',
    software: 'Halo Santovate, saya ingin demo Santovate CRM untuk Software House / Agency. Saya ingin melihat alur discovery, demo, proposal, dan follow-up WhatsApp.',
    distributor: 'Halo Santovate, saya ingin demo Santovate CRM untuk Distributor & B2B Sales. Mohon tunjukkan account list, quotation, repeat order, dan campaign WhatsApp.',
    parfum: 'Halo Santovate, saya ingin demo Santovate CRM untuk Parfum & Retail. Saya ingin melihat katalog produk, follow-up customer, order pipeline, dan template WhatsApp dengan gambar.',
};

export default function WhatsAppFloating() {
    const [open, setOpen] = useState(false);
    const openWa = (key) => window.open(`https://wa.me/6281293047587?text=${encodeURIComponent(options[key])}`, '_blank', 'noopener,noreferrer');
    return <div className="sv-wa-float">
        {open && <div className="sv-wa-picker">
            <strong>Pilih jenis bisnis</strong><small>Pesan WhatsApp akan disesuaikan otomatis.</small>
            <button onClick={() => openWa('logistics')}>Logistics & Freight</button>
            <button onClick={() => openWa('software')}>Software & Agency</button>
            <button onClick={() => openWa('distributor')}>Distributor & B2B</button>
            <button onClick={() => openWa('parfum')}>Parfum & Retail</button>
        </div>}
        <button className="sv-wa-trigger" aria-label="Chat WhatsApp" onClick={() => setOpen(!open)}><Icon name="whatsapp" size={24}/></button>
    </div>;
}
