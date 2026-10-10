import { Head, Link } from '@inertiajs/react';
import PublicShell from '../Public/PublicShell';

export default function InvitationUnavailable() {
    return <PublicShell title="Undangan Tidak Berlaku">
        <Head title="Undangan Tidak Berlaku">
            <meta name="robots" content="noindex,nofollow,noarchive"/>
            <meta name="referrer" content="no-referrer"/>
        </Head>
        <section className="sv-invite-public sv-invite-unavailable" aria-labelledby="invite-unavailable-heading">
            <div className="sv-invite-expired-icon" aria-hidden="true">!</div>
            <span className="eyebrow">Santovate CRM · Keamanan akses</span>
            <h1 id="invite-unavailable-heading">Tautan undangan tidak berlaku</h1>
            <p>Undangan ini mungkin sudah digunakan, dibatalkan, atau melewati batas waktunya. Demi keamanan, informasi akun dan organisasi tidak ditampilkan.</p>
            <p>Jika akun Anda telah aktif, silakan masuk. Jika masih memerlukan akses, hubungi administrator workspace untuk memperoleh undangan baru.</p>
            <div className="sv-invite-unavailable-actions">
                <Link className="btn btn-primary" href="/login">Masuk ke Santovate CRM</Link>
                <Link className="btn btn-secondary" href="/">Kembali ke beranda</Link>
            </div>
        </section>
    </PublicShell>;
}
