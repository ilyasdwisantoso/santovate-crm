import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar, EmptyState, PriorityBadge, ScoreDots, StatusBadge } from '../../Components/Ui';
import { dateTime, isDue, money } from '../../Utils/format';
import {
    prospectFilterState, prospectFilterQuery, prospectActiveChips,
    removeProspectFilter,
} from '../../Utils/prospectFilters';

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;
    return <nav className="pagination" aria-label="Halaman prospek">
        {links.map((link, index) => {
            const label = index === 0 ? 'Sebelumnya' : index === links.length - 1 ? 'Berikutnya' :
                String(link.label).replace(/<[^>]*>/g, '').trim();
            return link.url
                ? <Link key={index} href={link.url} preserveScroll className={link.active ? 'active' : ''}
                    aria-label={`Halaman ${label}`} aria-current={link.active ? 'page' : undefined}>{label}</Link>
                : <span key={index} className="disabled" aria-disabled="true">{label}</span>;
        })}
    </nav>;
}

function ProspectTable({ prospects }) {
    return <div className="prospect-table-wrap desktop-only">
        <table className="prospect-table">
            <caption className="sr-only">Daftar prospek sesuai pencarian dan filter</caption>
            <thead><tr><th>Perusahaan</th><th>Kontak / PIC</th><th>Prioritas</th><th>Status</th><th>Account Executive</th><th>Follow-up</th><th>Potensi</th><th><span className="sr-only">Detail</span></th></tr></thead>
            <tbody>{prospects.map((p) => <tr key={p.id}>
                <td><Link href={`/prospects/${p.id}`} className="company-cell"><span className="company-avatar">{p.company_name?.[0]}</span><div><strong>{p.company_name}</strong><small>{[p.city, p.service].filter(Boolean).join(' · ') || 'Data belum lengkap'}</small></div></Link></td>
                <td><div className="stacked-cell"><strong>{p.contact_name || 'Belum ada PIC'}</strong><small>{p.contact_position || p.email || p.phone || 'Kontak belum ditemukan'}</small></div></td>
                <td><div className="priority-cell"><PriorityBadge priority={p.priority}/><ScoreDots value={p.total_score >= 7 ? 3 : p.total_score >= 4 ? 2 : p.total_score > 0 ? 1 : 0}/><small>{p.total_score}/9</small></div></td>
                <td><StatusBadge status={p.status} label={p.status_label}/></td>
                <td>{p.assigned_user ? <div className="user-cell"><Avatar name={p.assigned_user.name} size="sm"/><span>{p.assigned_user.name}</span></div> : <span className="muted">Belum ditugaskan</span>}</td>
                <td><div className={`stacked-cell ${isDue(p.next_follow_up_at) ? 'due' : ''}`}><strong>{p.next_follow_up_at ? dateTime(p.next_follow_up_at) : 'Belum dijadwalkan'}</strong><small>{isDue(p.next_follow_up_at) ? 'Perlu tindakan' : 'Terjadwal'}</small></div></td>
                <td><strong>{money(p.estimated_deal_value)}</strong></td>
                <td><Link className="icon-button" href={`/prospects/${p.id}`} aria-label={`Detail ${p.company_name}`}><Icon name="chevron" size={17}/></Link></td>
            </tr>)}</tbody>
        </table>
    </div>;
}

function MobileProspectList({ prospects }) {
    return <div className="mobile-prospect-list mobile-only">{prospects.map((p) =>
        <Link href={`/prospects/${p.id}`} className="mobile-prospect-card" key={p.id}>
            <div className="mobile-prospect-top"><div className="company-cell"><span className="company-avatar">{p.company_name?.[0]}</span><div><strong>{p.company_name}</strong><small>{[p.city, p.service].filter(Boolean).join(' · ') || 'Data belum lengkap'}</small></div></div><span className="score-pill">{p.total_score}/9</span></div>
            <div className="mobile-badges"><PriorityBadge priority={p.priority}/><StatusBadge status={p.status} label={p.status_label}/></div>
            <div className="mobile-prospect-meta">
                <div><span>PIC</span><strong>{p.contact_name || 'Belum ada'}</strong></div>
                <div><span>Follow-up</span><strong className={isDue(p.next_follow_up_at) ? 'text-danger' : ''}>{p.next_follow_up_at ? dateTime(p.next_follow_up_at) : 'Belum ada'}</strong></div>
                <div><span>Potensi</span><strong>{money(p.estimated_deal_value)}</strong></div>
            </div>
        </Link>
    )}</div>;
}

export default function Index({ prospects, filters = {}, statuses = {}, qualificationStatuses = {}, priorities = {}, salesUsers = [] }) {
    const { auth } = usePage().props;
    const isAdmin = Boolean(auth.user.is_admin);
    const applied = prospectFilterState(filters);
    const [form, setForm] = useState(applied);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(applied.priority || applied.status || applied.qualification_status || applied.assigned_to));
    const chips = prospectActiveChips(applied, { statuses, qualifications: qualificationStatuses, priorities, salesUsers, isAdmin });
    const hasFilters = chips.length > 0;

    // Inertia back/forward navigation should synchronize draft fields to the URL.
    useEffect(() => { setForm(prospectFilterState(filters)); }, [
        filters.q, filters.priority, filters.status, filters.qualification_status,
        filters.assigned_to, filters.followup, filters.import_batch, filters.sort,
    ]);

    const navigate = (next) => router.get('/prospects', prospectFilterQuery(next), {
        replace: true, preserveState: true, preserveScroll: true,
    });
    const update = (key, value) => setForm((old) => ({ ...old, [key]: value }));
    const apply = (event) => { event.preventDefault(); navigate(form); };
    const clear = () => { setForm(prospectFilterState()); navigate({}); };
    const remove = (key) => navigate(removeProspectFilter(applied, key));
    const changeSort = (value) => { update('sort', value); navigate({ ...applied, sort: value }); };
    const toggleDue = () => { navigate({ ...applied, followup: applied.followup === 'due' ? '' : 'due' }); };
    const actions = <div className="action-group">
        {isAdmin && <Link href="/imports" className="btn btn-secondary hide-mobile"><Icon name="upload" size={17}/>Import Data</Link>}
        <Link href="/prospects/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah Prospek</Link>
    </div>;

    return <AppLayout title="Prospek" subtitle="Cari, tindak lanjuti dan kelola prospek tanpa kehilangan konteks." action={actions}>
        <Head title="Prospek"/>
        <section className="panel no-pad p942-panel" aria-label="Database prospek">
            <form onSubmit={apply} role="search" aria-label="Cari dan filter prospek">
                <div className="p942-toolbar">
                    <label className="p942-search" htmlFor="p942-search-input"><Icon name="search" size={18}/>
                        <input id="p942-search-input" name="q" type="search" maxLength={120} value={form.q}
                            onChange={e => update('q', e.target.value)}
                            placeholder="Cari bisnis, PIC, email, nomor WhatsApp..." autoComplete="off"/>
                    </label>
                    <div className="p942-toolbar-actions">
                        <button className="p942-toggle" type="button" onClick={() => setShowAdvanced(!showAdvanced)}
                            aria-expanded={showAdvanced} aria-controls="p942-advanced"><Icon name="filter" size={16}/>
                            {showAdvanced ? 'Tutup filter' : 'Filter lainnya'}
                        </button>
                        <button className={`p942-due ${applied.followup === 'due' ? 'is-active' : ''}`} type="button"
                            aria-pressed={applied.followup === 'due'} onClick={toggleDue}>
                            <Icon name="calendar" size={16}/>Jatuh tempo
                        </button>
                        <button className="p942-apply" type="submit"><Icon name="search" size={16}/>Terapkan pencarian</button>
                    </div>
                </div>
                {showAdvanced && <div id="p942-advanced" className="p942-advanced">
                    <div className="p942-field"><label htmlFor="p942-priority">Prioritas</label><select id="p942-priority" value={form.priority} onChange={e => update('priority', e.target.value)}><option value="">Semua prioritas</option>{Object.entries(priorities).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></div>
                    <div className="p942-field"><label htmlFor="p942-status">Status pipeline</label><select id="p942-status" value={form.status} onChange={e => update('status', e.target.value)}><option value="">Semua status</option>{Object.entries(statuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></div>
                    <div className="p942-field"><label htmlFor="p942-qualification">Kualifikasi</label><select id="p942-qualification" value={form.qualification_status} onChange={e => update('qualification_status', e.target.value)}><option value="">Semua kualifikasi</option>{Object.entries(qualificationStatuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></div>
                    {isAdmin && <div className="p942-field"><label htmlFor="p942-assigned">Account Executive</label><select id="p942-assigned" value={form.assigned_to} onChange={e => update('assigned_to', e.target.value)}><option value="">Semua AE</option><option value="unassigned">Belum ditugaskan</option>{salesUsers.map(user => <option key={user.id} value={user.id}>{user.name}</option>)}</select></div>}
                </div>}
            </form>

            {hasFilters && <div className="p942-filter-bar" aria-label="Filter aktif">
                <span className="p942-filter-caption">Aktif ({chips.length})</span>
                {chips.map(chip => <button key={chip.key} className="p942-chip" type="button"
                    aria-label={`Hapus filter ${chip.label}`} title={`Hapus ${chip.label}`} onClick={() => remove(chip.key)}>
                    <span>{chip.label}</span><Icon name="close" size={13}/>
                </button>)}
                <button className="p942-reset" type="button" onClick={clear}>Reset semua</button>
            </div>}

            <div className="p942-list-head">
                <div><strong>{prospects.total || 0} prospek ditemukan</strong><small>Hasil berdasarkan filter yang sedang diterapkan</small></div>
                <label className="p942-sort" htmlFor="p942-sort">Urutkan
                    <select id="p942-sort" value={applied.sort} onChange={e => changeSort(e.target.value)}>
                        <option value="score">Prioritas tertinggi</option>
                        <option value="recent">Terakhir diperbarui</option>
                        <option value="followup">Follow-up terdekat</option>
                        <option value="value">Potensi terbesar</option>
                    </select>
                </label>
            </div>

            {prospects.data.length ? <>
                <ProspectTable prospects={prospects.data}/>
                <MobileProspectList prospects={prospects.data}/>
                <div className="table-footer"><span>Menampilkan {prospects.from}–{prospects.to} dari {prospects.total} prospek</span><Pagination links={prospects.links}/></div>
            </> : <div className="p942-empty"><EmptyState icon="building"
                title={hasFilters ? 'Tidak ada prospek yang cocok' : 'Belum ada prospek'}
                description={hasFilters ? 'Coba hapus beberapa filter atau ubah kata pencarian.' : 'Tambahkan prospek baru atau impor data untuk mulai mengelola penjualan.'}
                action={hasFilters
                    ? <button className="btn btn-secondary" type="button" onClick={clear}>Hapus semua filter</button>
                    : <div className="empty-actions">{isAdmin && <Link href="/imports" className="btn btn-secondary"><Icon name="upload" size={17}/>Import Data</Link>}<Link href="/prospects/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah Prospek</Link></div>}/></div>}
        </section>
    </AppLayout>;
}
