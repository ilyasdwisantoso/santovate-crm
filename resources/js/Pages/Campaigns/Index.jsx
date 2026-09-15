import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

export default function Campaigns({
    campaigns = [],
    templates = [],
    eligibleCount = 0,
    setupReady = true,
    missingSchema = [],
    channelReady = false,
    channel = null,
}) {
    const form = useForm({
        name: '',
        template_id: templates[0]?.id || '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/campaigns', {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    const send = (campaign) => {
        if (!window.confirm(`Kirim campaign "${campaign.name}" ke recipient queued?`)) return;
        router.post(`/campaigns/${campaign.id}/send`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout
            title="WhatsApp Campaign"
            subtitle="Pengiriman massal hanya untuk kontak opt-in dan Meta template approved."
        >
            <Head title="WhatsApp Campaign"/>

            {!setupReady && (
                <section className="panel sv-system-warning">
                    <div className="panel-head">
                        <div>
                            <span className="eyebrow">Database repair required</span>
                            <h3>WA Campaign belum siap digunakan</h3>
                            <p>
                                Halaman tidak lagi melempar 500. Jalankan migration repair v4.0.2.4,
                                lalu buka halaman ini kembali.
                            </p>
                        </div>
                    </div>
                    <div className="sv-schema-list">
                        {missingSchema.map((item) => <code key={item}>{item}</code>)}
                    </div>
                    <p className="muted"><code>php artisan migrate</code></p>
                </section>
            )}

            {setupReady && (
                <>
                    <section className="sv-campaign-health-grid">
                        <article className="panel">
                            <span className="eyebrow">Eligible audience</span>
                            <strong className="sv-big-number">{eligibleCount}</strong>
                            <p>Kontak memiliki nomor WhatsApp, opt-in aktif, dan belum opt-out.</p>
                        </article>
                        <article className="panel">
                            <span className="eyebrow">Approved templates</span>
                            <strong className="sv-big-number">{templates.length}</strong>
                            <p>Hanya template Meta berstatus approved yang dapat dipilih.</p>
                        </article>
                        <article className="panel">
                            <span className="eyebrow">Cloud API</span>
                            <strong className={channelReady ? 'text-success' : 'text-danger'}>
                                {channelReady ? 'Ready' : 'Belum siap'}
                            </strong>
                            <p>
                                {channelReady
                                    ? `Phone Number ID ${channel?.phone_number_id || 'aktif'}`
                                    : 'Aktifkan channel Meta Cloud API sebelum menekan Kirim Campaign.'}
                            </p>
                            {!channelReady && (
                                <Link href="/settings/whatsapp" className="btn btn-secondary">
                                    <Icon name="whatsapp" size={16}/> Buka WhatsApp API
                                </Link>
                            )}
                        </article>
                    </section>

                    <form className="panel sv-settings-form" onSubmit={submit}>
                        <div className="panel-head">
                            <div>
                                <span className="eyebrow">Campaign baru</span>
                                <h3>Buat audience dari kontak yang sudah opt-in</h3>
                                <p>Campaign dibuat sebagai draft terlebih dahulu. Pengiriman dilakukan terpisah melalui queue.</p>
                            </div>
                        </div>

                        <div className="form-grid two">
                            <label className="field">
                                <span>Nama campaign</span>
                                <input
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    placeholder="Contoh: Follow-up September"
                                />
                                {form.errors.name && <small className="text-danger">{form.errors.name}</small>}
                            </label>

                            <label className="field">
                                <span>Approved Meta template</span>
                                <select
                                    value={form.data.template_id}
                                    onChange={(event) => form.setData('template_id', event.target.value)}
                                >
                                    <option value="">Pilih template</option>
                                    {templates.map((template) => (
                                        <option key={template.id} value={template.id}>
                                            {template.name} · {template.meta_template_name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.template_id && <small className="text-danger">{form.errors.template_id}</small>}
                            </label>
                        </div>

                        {templates.length === 0 && (
                            <div className="sv-inline-warning">
                                Belum ada approved Meta template. Atur template pada Follow Up/WhatsApp sebelum membuat campaign.
                            </div>
                        )}

                        <button
                            className="btn btn-primary"
                            disabled={form.processing || !form.data.name || !form.data.template_id || templates.length === 0}
                        >
                            Buat Campaign
                        </button>
                    </form>

                    <section className="panel">
                        <div className="panel-head">
                            <div>
                                <span className="eyebrow">Campaign history</span>
                                <h3>{campaigns.length} campaign</h3>
                            </div>
                        </div>

                        <div className="sv-campaign-list">
                            {campaigns.length === 0 && (
                                <div className="empty-state">
                                    <Icon name="whatsapp" size={28}/>
                                    <h3>Belum ada campaign</h3>
                                    <p>Buat campaign pertama setelah Meta template approved dan audience opt-in tersedia.</p>
                                </div>
                            )}

                            {campaigns.map((campaign) => (
                                <article key={campaign.id}>
                                    <div>
                                        <strong>{campaign.name}</strong>
                                        <small>{campaign.template?.name || 'Template tidak tersedia'}</small>
                                    </div>
                                    <div className="sv-campaign-stats">
                                        <span>{campaign.total_recipients ?? campaign.recipients_count ?? 0} total</span>
                                        <span>{campaign.sent_count || 0} sent</span>
                                        <span>{campaign.delivered_count || 0} delivered</span>
                                        <span>{campaign.read_count || 0} read</span>
                                        <span>{campaign.failed_count || 0} failed</span>
                                    </div>
                                    <b>{campaign.status}</b>
                                    {campaign.status === 'draft' && (
                                        <button
                                            type="button"
                                            className="btn btn-primary"
                                            disabled={!channelReady}
                                            onClick={() => send(campaign)}
                                        >
                                            Kirim Campaign
                                        </button>
                                    )}
                                </article>
                            ))}
                        </div>
                    </section>
                </>
            )}
        </AppLayout>
    );
}
