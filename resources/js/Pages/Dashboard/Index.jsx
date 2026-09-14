import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { MetricCard, PriorityBadge, Progress, StatusBadge, Avatar, EmptyState } from '../../Components/Ui';
import { compactMoney, dateTime, isDue, money } from '../../Utils/format';

function PerformanceCard({ performance, name, compact = false }) {
    if (!performance) return null;

    const items = [
        ['Dihubungi', 'contacted'],
        ['Meeting', 'meetings'],
        ['Proposal', 'proposals'],
        ['Deal', 'deals'],
        ['Revenue', 'revenue'],
    ];

    return <article className={`panel target-overview dashboard-performance-card ${compact ? 'target-compact' : ''}`}>
        <div className="panel-head dashboard-section-head">
            <div>
                <span className="eyebrow">Target bulan ini</span>
                <h3>{name || performance.period.label}</h3>
            </div>
            <div className="score-ring dashboard-score-ring" style={{ '--score': `${performance.score * 3.6}deg` }}>
                <span>{performance.score}%</span>
            </div>
        </div>

        <div className="target-lines">
            {items.map(([label, key]) => <div className="target-line" key={key}>
                <div className="target-line-copy">
                    <span>{label}</span>
                    <strong>
                        {key === 'revenue'
                            ? `${compactMoney(performance.actual[key])} / ${compactMoney(performance.goals[key])}`
                            : `${performance.actual[key]} / ${performance.goals[key]}`}
                    </strong>
                </div>
                <Progress value={performance.progress[key]} />
            </div>)}
        </div>

        {!compact && <div className="target-footer dashboard-target-footer">
            <span><b>{performance.actual.active_companies}</b> perusahaan aktif</span>
            <span className={performance.actual.overdue_followups > 0 ? 'text-danger' : ''}>
                <b>{performance.actual.overdue_followups}</b> overdue
            </span>
            <Link href="/targets">Lihat target <Icon name="chevron" size={15} /></Link>
        </div>}
    </article>;
}

function MobileProspectCard({ prospect }) {
    return <Link href={`/prospects/${prospect.id}`} className="dashboard-prospect-card">
        <div className="dashboard-prospect-card-head">
            <div className="company-cell">
                <span className="company-avatar">{prospect.company_name?.[0]}</span>
                <div>
                    <strong>{prospect.company_name}</strong>
                    <small>{[prospect.city, prospect.service].filter(Boolean).join(' · ') || 'Belum dilengkapi'}</small>
                </div>
            </div>
            <Icon name="chevron" size={17} />
        </div>
        <div className="dashboard-prospect-meta">
            <span><small>Status</small><StatusBadge status={prospect.status} label={prospect.status_label} /></span>
            <span><small>Skor</small><strong>{prospect.total_score}/9</strong></span>
            <span><small>Potensi</small><strong>{money(prospect.estimated_deal_value)}</strong></span>
        </div>
        <div className="dashboard-prospect-owner">
            <Avatar
                name={prospect.assigned_user?.name || 'Belum diassign'}
                email={prospect.assigned_user?.email}
                initialsText={prospect.assigned_user?.profile_initials}
                size="sm"
            />
            <span>{prospect.assigned_user?.name || 'Belum diassign'}</span>
        </div>
    </Link>;
}

function FollowUpMiniCard({ prospect }) {
    return <Link href={`/prospects/${prospect.id}`} className="dashboard-followup-card">
        <span className={`followup-dot ${isDue(prospect.next_follow_up_at) ? 'overdue' : ''}`} />
        <div className="dashboard-followup-copy">
            <strong>{prospect.company_name}</strong>
            <small>{dateTime(prospect.next_follow_up_at)}</small>
        </div>
        <PriorityBadge priority={prospect.priority} />
    </Link>;
}

export default function Dashboard({ stats, pipeline, followUps, topProspects, salesPerformance, teamPerformance }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const firstName = user.name?.split(' ')[0] || user.name;

    return <AppLayout
        title={`Halo, ${firstName}`}
        subtitle="Fokus pada prospek yang paling dekat ke percakapan dan revenue."
        action={<Link href="/prospects/create" className="btn btn-primary dashboard-add-prospect"><Icon name="plus" size={17} />Tambah Prospek</Link>}
    >
        <Head title="Dashboard" />

        <section className="metric-grid dashboard-metric-grid-v24">
            <MetricCard label="Total Prospek" value={stats.total} helper="database yang dapat Anda akses" icon="building" />
            <MetricCard label="Prioritas Tinggi" value={stats.priority_high} helper="layak disentuh lebih dulu" icon="target" accent="red" />
            <MetricCard label="Perlu Follow-up" value={stats.need_followup} helper="jatuh tempo sampai hari ini" icon="calendar" accent="amber" />
            <MetricCard label="Meeting → Negosiasi" value={stats.meeting_plus} helper="prospek tengah–akhir funnel" icon="briefcase" accent="blue" />
            <MetricCard label="Deal" value={stats.deals} helper="prospek berhasil ditutup" icon="check" accent="green" />
            <MetricCard label="Nilai Pipeline" value={`Rp${compactMoney(stats.pipeline_value)}`} helper="estimasi potensi open pipeline" icon="target" accent="violet" />
        </section>

        <section className="dashboard-layout dashboard-layout-v24">
            <div className="dashboard-main">
                {!user.is_admin && <PerformanceCard performance={salesPerformance} />}

                {user.is_admin && <section className="panel dashboard-team-panel">
                    <div className="panel-head dashboard-section-head">
                        <div>
                            <span className="eyebrow">Team performance</span>
                            <h3>Progress Account Executive bulan ini</h3>
                        </div>
                        <Link href="/targets" className="text-link">Kelola target AE <Icon name="chevron" size={15} /></Link>
                    </div>

                    {teamPerformance.length
                        ? <div className="team-performance-list">
                            {teamPerformance.slice(0, 5).map(({ user: member, performance }) => <div className="team-performance-row dashboard-team-row" key={member.id}>
                                <Avatar name={member.name} />
                                <div className="team-performance-copy">
                                    <div>
                                        <strong>{member.name}</strong>
                                        <span>{performance.actual.deals} deal · {money(performance.actual.revenue)}</span>
                                    </div>
                                    <Progress value={performance.score} />
                                </div>
                                <b>{performance.score}%</b>
                            </div>)}
                        </div>
                        : <EmptyState title="Belum ada Account Executive" description="Tambahkan Account Executive untuk mulai mengukur target." />}
                </section>}

                <section className="panel dashboard-funnel-panel">
                    <div className="panel-head dashboard-section-head">
                        <div>
                            <span className="eyebrow">Funnel</span>
                            <h3>Pergerakan pipeline</h3>
                        </div>
                        <Link href="/pipeline" className="text-link">Buka pipeline <Icon name="chevron" size={15} /></Link>
                    </div>
                    <div className="funnel-strip dashboard-funnel-scroll">
                        {pipeline.slice(0, 9).map((item, i) => <div className="funnel-item" key={item.key}>
                            <div className="funnel-count">{item.count}</div>
                            <span>{item.label}</span>
                            {i < 8 && <Icon name="chevron" size={15} />}
                        </div>)}
                    </div>
                </section>

                <section className="panel dashboard-prospects-panel">
                    <div className="panel-head dashboard-section-head">
                        <div>
                            <span className="eyebrow">Prioritas</span>
                            <h3>Prospek terbaik untuk ditindaklanjuti</h3>
                        </div>
                        <Link href="/prospects?priority=tinggi" className="text-link">Lihat semua <Icon name="chevron" size={15} /></Link>
                    </div>

                    {topProspects.length ? <>
                        <div className="clean-table-wrap desktop-only">
                            <table className="clean-table">
                                <thead><tr><th>Perusahaan</th><th>Account Executive</th><th>Status</th><th>Skor</th><th>Potensi</th><th /></tr></thead>
                                <tbody>{topProspects.map((prospect) => <tr key={prospect.id}>
                                    <td><div className="company-cell"><span className="company-avatar">{prospect.company_name?.[0]}</span><div><strong>{prospect.company_name}</strong><small>{[prospect.city, prospect.service].filter(Boolean).join(' · ') || 'Belum dilengkapi'}</small></div></div></td>
                                    <td>{prospect.assigned_user?.name || 'Belum diassign'}</td>
                                    <td><StatusBadge status={prospect.status} label={prospect.status_label} /></td>
                                    <td><span className="score-pill">{prospect.total_score}/9</span></td>
                                    <td>{money(prospect.estimated_deal_value)}</td>
                                    <td><Link className="icon-button" href={`/prospects/${prospect.id}`}><Icon name="chevron" size={17} /></Link></td>
                                </tr>)}</tbody>
                            </table>
                        </div>
                        <div className="mobile-only dashboard-prospect-mobile-list">
                            {topProspects.map((prospect) => <MobileProspectCard key={prospect.id} prospect={prospect} />)}
                        </div>
                    </> : <EmptyState title="Belum ada prospek prioritas" description="Tambahkan dan beri skor prospek untuk melihat prioritas di dashboard." />}
                </section>
            </div>

            <aside className="dashboard-side dashboard-side-v24">
                <section className="panel sticky-panel dashboard-followup-panel">
                    <div className="panel-head dashboard-section-head">
                        <div>
                            <span className="eyebrow">Account Executive queue</span>
                            <h3>Follow-up terdekat</h3>
                        </div>
                        <span className="count-bubble">{followUps.length}</span>
                    </div>

                    {followUps.length
                        ? <div className="followup-list dashboard-followup-list">{followUps.map((prospect) => <FollowUpMiniCard key={prospect.id} prospect={prospect} />)}</div>
                        : <EmptyState icon="calendar" title="Follow-up aman" description="Tidak ada follow-up jatuh tempo dalam daftar ini." />}

                    <Link href="/follow-ups" className="btn btn-soft btn-block">Buka Follow Up Queue</Link>
                </section>
            </aside>
        </section>
    </AppLayout>;
}
