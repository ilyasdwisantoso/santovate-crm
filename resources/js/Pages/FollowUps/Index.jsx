import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar, EmptyState, PriorityBadge, StatusBadge } from '../../Components/Ui';
import { dateTime } from '../../Utils/format';

const normalizeWhatsApp = (value = '') => {
    let digits = String(value).replace(/\D/g, '');
    if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
    if (digits.startsWith('8')) digits = `62${digits}`;
    return digits;
};

const daysSince = (value) => {
    if (!value) return 0;
    return Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 86400000));
};

const queueCopy = {
    response_needed: {
        label: 'Perlu Balas',
        short: 'Balas',
        tone: 'danger',
        icon: 'whatsapp',
        description: 'Customer sudah merespons tetapi Account Executive belum membalas lagi.',
    },
    lead_age_due: {
        label: 'Wajib Feedback H-3',
        short: 'H-3',
        tone: 'violet',
        icon: 'alert',
        description: 'Lead sudah melewati batas umur tanpa aktivitas follow-up. Account Executive wajib memberi feedback.',
    },
    no_reply: {
        label: 'Belum Ada Feedback',
        short: 'No Reply',
        tone: 'warning',
        icon: 'alert',
        description: 'Sudah melewati jeda follow-up sejak kontak terakhir dan belum ada balasan customer.',
    },
    scheduled: {
        label: 'Terjadwal',
        short: 'Jadwal',
        tone: 'info',
        icon: 'calendar',
        description: 'Follow-up yang dijadwalkan dan sudah jatuh tempo hari ini.',
    },
};

function ComposerModal({ prospect, queueType, templates, onClose }) {
    const isReply = queueType === 'response_needed';
    const isLeadAge = queueType === 'lead_age_due';
    const suggestedId = prospect.suggested_template_id ? String(prospect.suggested_template_id) : '';
    const [templateId, setTemplateId] = useState(suggestedId);
    const [opened, setOpened] = useState(false);

    const form = useForm({
        message: prospect.suggested_message || '',
        queue_type: queueType,
        feedback_note: '',
        next_follow_up_days: isLeadAge ? 3 : 5,
    });

    const waNumber = normalizeWhatsApp(prospect.phone);

    const changeTemplate = (event) => {
        const value = event.target.value;
        setTemplateId(value);
        if (value && prospect.template_messages?.[value] !== undefined) {
            form.setData('message', prospect.template_messages[value]);
        }
    };

    const openWhatsApp = () => {
        if (!waNumber || !form.data.message.trim()) return;
        const url = `https://wa.me/${waNumber}?text=${encodeURIComponent(form.data.message.trim())}`;
        window.open(url, '_blank', 'noopener,noreferrer');
        setOpened(true);
    };

    const markSent = () => {
        form.post(`/follow-ups/${prospect.id}/sent`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return <div className="modal-backdrop" onClick={onClose}>
        <section className="followup-composer followup-composer-v24" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
                <div>
                    <span className="eyebrow">{isLeadAge ? `Lead age ${prospect.lead_age_days} hari · feedback wajib` : isReply ? 'Customer sudah membalas' : 'Review sebelum kirim'}</span>
                    <h3>{isReply ? 'Siapkan balasan WhatsApp' : 'Siapkan follow-up WhatsApp'}</h3>
                    <p>{prospect.company_name} · {prospect.contact_name || 'PIC belum diketahui'}</p>
                </div>
                <button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20} /></button>
            </div>

            <div className="composer-recipient">
                <Avatar name={prospect.contact_name || prospect.company_name} />
                <div><span>Dikirim ke</span><strong>{prospect.phone || 'Nomor WhatsApp belum tersedia'}</strong></div>
                <StatusBadge status={prospect.status} label={prospect.status_label} />
            </div>

            <label className="field">
                <span>Template pesan</span>
                <select value={templateId} onChange={changeTemplate}>
                    <option value="">Gunakan draft saat ini</option>
                    {templates.map((template) => <option key={template.id} value={String(template.id)}>
                        {template.name} · {template.wait_days} hari
                    </option>)}
                </select>
                <small className="field-hint">Pilih template sebagai draft. Pesan tetap bisa diedit bebas sebelum membuka WhatsApp.</small>
            </label>

            <label className="field">
                <span>Review & edit pesan WhatsApp</span>
                <textarea rows="10" value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} />
                <small className="field-hint">CRM tidak mengirim otomatis. Account Executive wajib review, edit bila perlu, lalu kirim melalui WhatsApp.</small>
                {form.errors.message && <small className="field-error">{form.errors.message}</small>}
            </label>

            {isLeadAge && <label className="field followup-required-feedback">
                <span>Feedback Account Executive <b>* wajib</b></span>
                <textarea
                    rows="4"
                    value={form.data.feedback_note}
                    onChange={(e) => form.setData('feedback_note', e.target.value)}
                    placeholder="Contoh: Lead belum pernah disentuh. Nomor valid, saya kirim pesan awal hari ini dan akan follow-up lagi 3 hari."
                />
                <small className="field-hint">Lead sudah H-{prospect.lead_age_days}. Feedback ini menjadi audit pekerjaan AE dan tersimpan di activity prospect.</small>
                {form.errors.feedback_note && <small className="field-error">{form.errors.feedback_note}</small>}
            </label>}

            <label className="field compact-field">
                <span>Jika belum ada respons lagi, ingatkan setelah</span>
                <select value={form.data.next_follow_up_days} onChange={(e) => form.setData('next_follow_up_days', Number(e.target.value))}>
                    <option value={1}>1 hari</option>
                    <option value={3}>3 hari</option>
                    <option value={5}>5 hari</option>
                    <option value={7}>7 hari</option>
                    <option value={14}>14 hari</option>
                    <option value={0}>Tidak dijadwalkan</option>
                </select>
            </label>

            {!waNumber && <div className="alert alert-error">Nomor WhatsApp belum tersedia. Lengkapi nomor prospect sebelum mengirim pesan.</div>}
            {opened && <div className="composer-confirmation">
                <Icon name="check" size={18} />
                <div><strong>WhatsApp sudah dibuka</strong><small>Setelah benar-benar menekan Send di WhatsApp, kembali ke CRM dan tandai sebagai sudah dikirim.</small></div>
            </div>}

            <div className="modal-actions composer-actions">
                <button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button>
                <button type="button" className="btn btn-whatsapp" disabled={!waNumber || !form.data.message.trim()} onClick={openWhatsApp}>
                    <Icon name="whatsapp" size={17} />Buka WhatsApp
                </button>
                <button
                    type="button"
                    className="btn btn-primary"
                    disabled={!opened || form.processing || (isLeadAge && !form.data.feedback_note.trim())}
                    onClick={markSent}
                >
                    <Icon name="check" size={17} />{form.processing ? 'Menyimpan...' : 'Tandai Sudah Dikirim'}
                </button>
            </div>
        </section>
    </div>;
}

function ReplyModal({ prospect, onClose }) {
    const form = useForm({ note: '' });
    const submit = (event) => {
        event.preventDefault();
        form.post(`/follow-ups/${prospect.id}/customer-reply`, { preserveScroll: true, onSuccess: onClose });
    };

    return <div className="modal-backdrop" onClick={onClose}>
        <form className="modal-card followup-reply-modal" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
            <div className="modal-head">
                <div><span className="eyebrow">Update feedback</span><h3>Catat balasan customer</h3><p>{prospect.company_name}</p></div>
                <button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20} /></button>
            </div>
            <label className="field">
                <span>Ringkasan balasan customer</span>
                <textarea rows="6" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Contoh: Customer tertarik dan meminta demo minggu depan..." />
            </label>
            <div className="modal-actions">
                <button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button>
                <button className="btn btn-primary" disabled={form.processing}>Simpan & Masukkan ke Perlu Balas</button>
            </div>
        </form>
    </div>;
}

function FeedbackModal({ prospect, onClose }) {
    const form = useForm({ feedback_status: 'no_answer', feedback_note: '', next_follow_up_days: 3 });
    const submit = (event) => {
        event.preventDefault();
        form.post(`/follow-ups/${prospect.id}/feedback`, { preserveScroll: true, onSuccess: onClose });
    };

    return <div className="modal-backdrop" onClick={onClose}>
        <form className="followup-composer feedback-modal-v24" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
            <div className="modal-head">
                <div>
                    <span className="eyebrow">Lead age {prospect.lead_age_days} hari</span>
                    <h3>Feedback wajib Account Executive</h3>
                    <p>{prospect.company_name} · feedback harus dicatat agar lead keluar dari antrean H-3.</p>
                </div>
                <button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20} /></button>
            </div>

            <label className="field">
                <span>Hasil pengecekan</span>
                <select value={form.data.feedback_status} onChange={(e) => form.setData('feedback_status', e.target.value)}>
                    <option value="no_answer">Belum ada jawaban</option>
                    <option value="invalid_contact">Kontak tidak valid</option>
                    <option value="need_research">Perlu riset tambahan</option>
                    <option value="follow_up_later">Follow-up lagi nanti</option>
                    <option value="interested">Ada potensi / tertarik</option>
                    <option value="not_interested">Belum tertarik</option>
                </select>
            </label>

            <label className="field">
                <span>Catatan feedback <b>* wajib</b></span>
                <textarea rows="6" value={form.data.feedback_note} onChange={(e) => form.setData('feedback_note', e.target.value)} placeholder="Tuliskan tindakan yang sudah dilakukan, hasil pengecekan, dan rencana berikutnya..." />
                {form.errors.feedback_note && <small className="field-error">{form.errors.feedback_note}</small>}
            </label>

            <label className="field compact-field">
                <span>Jadwalkan pengecekan/follow-up berikutnya</span>
                <select value={form.data.next_follow_up_days} onChange={(e) => form.setData('next_follow_up_days', Number(e.target.value))}>
                    <option value={1}>Besok</option>
                    <option value={3}>3 hari</option>
                    <option value={5}>5 hari</option>
                    <option value={7}>7 hari</option>
                    <option value={14}>14 hari</option>
                    <option value={0}>Tidak dijadwalkan</option>
                </select>
            </label>

            <div className="modal-actions">
                <button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button>
                <button className="btn btn-primary" disabled={form.processing || !form.data.feedback_note.trim()}>
                    <Icon name="check" size={17} />Simpan Feedback
                </button>
            </div>
        </form>
    </div>;
}

function TemplateEditor({ template, onClose }) {
    const form = useForm({ name: template.name, wait_days: template.wait_days, message: template.message });
    const submit = (event) => {
        event.preventDefault();
        form.put(`/follow-up-templates/${template.id}`, { preserveScroll: true, onSuccess: onClose });
    };

    return <div className="modal-backdrop" onClick={onClose}>
        <form className="followup-composer" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
            <div className="modal-head">
                <div><span className="eyebrow">Template CRM</span><h3>{template.name}</h3><p>Placeholder: {'{contact_name}'}, {'{company_name}'}, {'{ae_name}'}, {'{ae_phone}'}, {'{lead_age_days}'}, {'{service}'}.</p></div>
                <button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20} /></button>
            </div>
            <div className="form-grid single-mobile">
                <label className="field"><span>Nama template</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                <label className="field"><span>Trigger / jeda hari</span><input type="number" min="0" max="60" value={form.data.wait_days} onChange={(e) => form.setData('wait_days', Number(e.target.value))} /><small className="field-hint">Untuk template H-3, ubah menjadi 3 untuk memunculkan lead setelah 3 hari.</small></label>
            </div>
            <label className="field"><span>Isi template WhatsApp</span><textarea rows="10" value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} /></label>
            {Object.values(form.errors).length > 0 && <div className="alert alert-error">{Object.values(form.errors)[0]}</div>}
            <div className="modal-actions"><button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button><button className="btn btn-primary" disabled={form.processing}>Simpan Template</button></div>
        </form>
    </div>;
}

function conditionText(prospect, type) {
    if (type === 'response_needed') return `Customer membalas ${dateTime(prospect.last_customer_reply_at)}`;
    if (type === 'lead_age_due') return `Lead berumur ${prospect.lead_age_days} hari tanpa aktivitas follow-up`;
    if (type === 'scheduled') return `Jadwal ${dateTime(prospect.next_follow_up_at)}`;
    return `${daysSince(prospect.last_outbound_at)} hari tanpa feedback customer`;
}

function QueueRow({ prospect, type, onCompose, onReply, onFeedback }) {
    const isResponse = type === 'response_needed';
    const isLeadAge = type === 'lead_age_due';
    const snoozeTomorrow = () => router.post(`/follow-ups/${prospect.id}/snooze`, { until: new Date(Date.now() + 86400000).toISOString() }, { preserveScroll: true });

    return <tr className={isLeadAge ? 'h3-row' : ''}>
        <td><Link href={`/prospects/${prospect.id}`} className="company-cell"><span className="company-avatar">{prospect.company_name?.[0]}</span><div><strong>{prospect.company_name}</strong><small>{[prospect.city, prospect.service].filter(Boolean).join(' · ') || 'Data belum lengkap'}</small></div></Link></td>
        <td><div className="stacked-cell"><strong>{prospect.contact_name || 'PIC belum ada'}</strong><small>{prospect.phone || 'Nomor WA belum ada'}</small></div></td>
        <td><PriorityBadge priority={prospect.priority} /></td>
        <td><div className={`followup-age ${isResponse || isLeadAge ? 'urgent' : ''}`}><strong>{queueCopy[type]?.label}</strong><small>{conditionText(prospect, type)}</small></div></td>
        <td>{prospect.assigned_user ? <div className="user-cell"><Avatar name={prospect.assigned_user.name} email={prospect.assigned_user.email} initialsText={prospect.assigned_user.profile_initials} size="sm" /><span>{prospect.assigned_user.name}</span></div> : <span className="muted">Belum ada AE</span>}</td>
        <td><div className="row-actions">
            <button className="btn btn-sm btn-primary" onClick={() => onCompose(prospect, type)}><Icon name="whatsapp" size={15} />{isResponse ? 'Siapkan Balasan' : 'Review Pesan'}</button>
            {isLeadAge && <button className="btn btn-sm btn-secondary h3-feedback-btn" onClick={() => onFeedback(prospect)}><Icon name="edit" size={15} />Feedback</button>}
            {!isResponse && !isLeadAge && <button className="btn btn-sm btn-secondary" onClick={() => onReply(prospect)}>Customer Membalas</button>}
            <button className="icon-button" title="Ingatkan besok" onClick={snoozeTomorrow}><Icon name="calendar" size={16} /></button>
        </div></td>
    </tr>;
}

function MobileQueueCard({ prospect, type, onCompose, onReply, onFeedback }) {
    const isResponse = type === 'response_needed';
    const isLeadAge = type === 'lead_age_due';
    const snoozeTomorrow = () => router.post(`/follow-ups/${prospect.id}/snooze`, { until: new Date(Date.now() + 86400000).toISOString() }, { preserveScroll: true });

    return <article className={`followup-mobile-card ${isResponse ? 'urgent' : ''} ${isLeadAge ? 'h3-card' : ''}`}>
        <div className="followup-mobile-head">
            <div className="company-cell"><span className="company-avatar">{prospect.company_name?.[0]}</span><div><strong>{prospect.company_name}</strong><small>{prospect.contact_name || 'PIC belum diketahui'}</small></div></div>
            <PriorityBadge priority={prospect.priority} />
        </div>
        {isLeadAge && <div className="h3-required-banner"><Icon name="alert" size={17} /><div><strong>Feedback wajib</strong><span>Lead sudah {prospect.lead_age_days} hari belum ditindaklanjuti.</span></div></div>}
        <div className="followup-mobile-info">
            <span><small>Status tugas</small><strong>{queueCopy[type]?.label}</strong></span>
            <span><small>WhatsApp</small><strong>{prospect.phone || 'Belum ada nomor'}</strong></span>
            <span><small>Account Executive</small><strong>{prospect.assigned_user?.name || 'Belum ditugaskan'}</strong></span>
        </div>
        <p className="followup-mobile-condition">{conditionText(prospect, type)}</p>
        <div className="followup-mobile-actions">
            <button className="btn btn-primary" onClick={() => onCompose(prospect, type)}><Icon name="whatsapp" size={16} />{isResponse ? 'Balas' : 'Review Pesan'}</button>
            {isLeadAge && <button className="btn btn-secondary" onClick={() => onFeedback(prospect)}><Icon name="edit" size={16} />Feedback</button>}
            {!isResponse && !isLeadAge && <button className="btn btn-secondary" onClick={() => onReply(prospect)}>Ada Balasan</button>}
            <button className="icon-button" onClick={snoozeTomorrow}><Icon name="calendar" size={17} /></button>
        </div>
    </article>;
}

export default function FollowUpsIndex({ queues, stats, templates, noReplyDays, leadAgeDays }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState(
        stats.response_needed > 0 ? 'response_needed'
            : stats.lead_age_due > 0 ? 'lead_age_due'
                : stats.no_reply > 0 ? 'no_reply'
                    : 'scheduled'
    );
    const [composer, setComposer] = useState(null);
    const [replyProspect, setReplyProspect] = useState(null);
    const [feedbackProspect, setFeedbackProspect] = useState(null);
    const [editingTemplate, setEditingTemplate] = useState(null);

    const tabs = useMemo(() => [
        { key: 'response_needed', ...queueCopy.response_needed, count: stats.response_needed },
        { key: 'lead_age_due', ...queueCopy.lead_age_due, count: stats.lead_age_due, description: `Lead berumur ${leadAgeDays}+ hari tanpa follow-up. Feedback AE wajib.` },
        { key: 'no_reply', ...queueCopy.no_reply, count: stats.no_reply, description: `Sudah ${noReplyDays}+ hari sejak kontak terakhir dan belum ada balasan customer.` },
        { key: 'scheduled', ...queueCopy.scheduled, count: stats.scheduled },
    ], [stats, noReplyDays, leadAgeDays]);

    const current = queues[tab] || [];
    const currentTab = tabs.find((item) => item.key === tab);

    return <AppLayout title="Follow Up" subtitle="Work queue harian Account Executive — review pesan, beri feedback, lalu kirim melalui WhatsApp.">
        <Head title="Follow Up Account Executive" />

        <section className="followup-summary-grid followup-summary-grid-v24">
            <article className="followup-summary-card danger"><span className="summary-icon"><Icon name="whatsapp" size={19} /></span><div><small>Perlu Balas</small><strong>{stats.response_needed}</strong><p>Customer sudah memberi feedback</p></div></article>
            <article className="followup-summary-card h3"><span className="summary-icon"><Icon name="alert" size={19} /></span><div><small>Wajib Feedback H-{leadAgeDays}</small><strong>{stats.lead_age_due}</strong><p>Lead belum pernah ditindaklanjuti</p></div></article>
            <article className="followup-summary-card warning"><span className="summary-icon"><Icon name="alert" size={19} /></span><div><small>Belum Ada Feedback</small><strong>{stats.no_reply}</strong><p>{noReplyDays}+ hari tanpa respons</p></div></article>
            <article className="followup-summary-card info"><span className="summary-icon"><Icon name="calendar" size={19} /></span><div><small>Terjadwal</small><strong>{stats.scheduled}</strong><p>Jatuh tempo hari ini</p></div></article>
        </section>

        <section className="panel followup-work-panel">
            <div className="followup-tabs followup-tabs-v24">
                {tabs.map((item) => <button key={item.key} className={`${tab === item.key ? 'active' : ''} tab-${item.tone}`} onClick={() => setTab(item.key)}>
                    <span>{item.label}</span><b>{item.count}</b>
                </button>)}
            </div>

            <div className="followup-tab-copy">
                <div><span className="eyebrow">Daily work queue</span><h3>{currentTab?.label}</h3><p>{currentTab?.description}</p></div>
                <Link href="/prospects" className="btn btn-secondary"><Icon name="building" size={16} />Semua Prospek</Link>
            </div>

            {current.length ? <>
                <div className="prospect-table-wrap desktop-only">
                    <table className="prospect-table followup-table">
                        <thead><tr><th>Perusahaan</th><th>PIC / WhatsApp</th><th>Prioritas</th><th>Kondisi</th><th>Account Executive</th><th>Tindakan</th></tr></thead>
                        <tbody>{current.map((prospect) => <QueueRow key={prospect.id} prospect={prospect} type={tab} onCompose={(p, type) => setComposer({ prospect: p, type })} onReply={setReplyProspect} onFeedback={setFeedbackProspect} />)}</tbody>
                    </table>
                </div>
                <div className="mobile-only followup-mobile-list">
                    {current.map((prospect) => <MobileQueueCard key={prospect.id} prospect={prospect} type={tab} onCompose={(p, type) => setComposer({ prospect: p, type })} onReply={setReplyProspect} onFeedback={setFeedbackProspect} />)}
                </div>
            </> : <EmptyState icon="check" title="Queue ini sudah bersih" description="Tidak ada prospect yang membutuhkan tindakan pada kategori ini." />}
        </section>

        <section className="panel followup-template-panel">
            <div className="panel-head dashboard-section-head">
                <div><span className="eyebrow">WhatsApp template library</span><h3>Template pesan follow-up</h3><p className="panel-subcopy">Semua template dapat dipilih oleh AE sebagai draft lalu direview dan diedit sebelum dikirim.</p></div>
                {auth.user.is_admin && <span className="badge badge-violet">Admin dapat mengubah trigger & isi</span>}
            </div>
            <div className="template-card-grid">
                {templates.map((template) => <article className={`template-card template-${template.trigger_type}`} key={template.id}>
                    <div className="template-card-head"><div><strong>{template.name}</strong><small>{template.trigger_type === 'lead_age' ? `Lead age H-${template.wait_days}` : `${template.wait_days} hari`}</small></div>{auth.user.is_admin && <button className="icon-button" onClick={() => setEditingTemplate(template)}><Icon name="edit" size={16} /></button>}</div>
                    <p>{template.message}</p>
                </article>)}
            </div>
        </section>

        {composer && <ComposerModal prospect={composer.prospect} queueType={composer.type} templates={templates} onClose={() => setComposer(null)} />}
        {replyProspect && <ReplyModal prospect={replyProspect} onClose={() => setReplyProspect(null)} />}
        {feedbackProspect && <FeedbackModal prospect={feedbackProspect} onClose={() => setFeedbackProspect(null)} />}
        {editingTemplate && <TemplateEditor template={editingTemplate} onClose={() => setEditingTemplate(null)} />}
    </AppLayout>;
}
