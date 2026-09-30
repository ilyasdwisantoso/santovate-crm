import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import {
    Avatar,
    EmptyState,
    MetricCard,
    PriorityBadge,
    Progress,
    QuickStat,
    SectionHeader,
    StatusBadge,
} from '../../Components/Ui';
import { compactMoney, dateTime, isDue, money } from '../../Utils/format';

function PerformanceCard({ performance, name }) {
    if (!performance) return null;

    const items = [
        ['Dihubungi', 'contacted'],
        ['Meeting', 'meetings'],
        ['Proposal', 'proposals'],
        ['Deal', 'deals'],
        ['Revenue', 'revenue'],
    ];

    return (
        <article className="panel premium-performance-card">
            <SectionHeader
                eyebrow="Performance"
                title={name || performance.period.label}
                description="Progress target bulan berjalan"
                action={<Link href="/targets" className="text-link">Detail <Icon name="chevron" size={14}/></Link>}
            />

            <div className="premium-performance-body">
                <div className="performance-score-block">
                    <div className="score-ring premium-score-ring" style={{ '--score': `${performance.score * 3.6}deg` }}>
                        <span>{performance.score}%</span>
                    </div>
                    <div>
                        <small>Overall target</small>
                        <strong>{performance.actual.deals} deal</strong>
                        <span>{compactMoney(performance.actual.revenue)} revenue</span>
                    </div>
                </div>

                <div className="target-lines premium-target-lines">
                    {items.map(([label, key]) => (
                        <div className="target-line" key={key}>
                            <div className="target-line-copy">
                                <span>{label}</span>
                                <strong>
                                    {key === 'revenue'
                                        ? `${compactMoney(performance.actual[key])} / ${compactMoney(performance.goals[key])}`
                                        : `${performance.actual[key]} / ${performance.goals[key]}`}
                                </strong>
                            </div>
                            <Progress value={performance.progress[key]}/>
                        </div>
                    ))}
                </div>
            </div>

            <div className="performance-footer">
                <span><b>{performance.actual.active_companies}</b> perusahaan aktif</span>
                <span className={performance.actual.overdue_followups > 0 ? 'text-danger' : ''}><b>{performance.actual.overdue_followups}</b> overdue</span>
            </div>
        </article>
    );
}

function FollowUpMiniCard({ prospect }) {
    const overdue = isDue(prospect.next_follow_up_at);
    return (
        <Link href={`/prospects/${prospect.id}`} className="dashboard-followup-card premium-followup-card">
            <span className={`followup-dot ${overdue ? 'overdue' : ''}`}/>
            <div className="dashboard-followup-copy">
                <strong>{prospect.company_name}</strong>
                <small>{dateTime(prospect.next_follow_up_at)}</small>
            </div>
            <PriorityBadge priority={prospect.priority}/>
        </Link>
    );
}

function MobileProspectCard({ prospect }) {
    return (
        <Link href={`/prospects/${prospect.id}`} className="dashboard-prospect-card premium-prospect-card">
            <div className="dashboard-prospect-card-head">
                <div className="company-cell">
                    <span className="company-avatar">{prospect.company_name?.[0]}</span>
                    <div>
                        <strong>{prospect.company_name}</strong>
                        <small>{[prospect.city, prospect.service].filter(Boolean).join(' · ') || 'Belum dilengkapi'}</small>
                    </div>
                </div>
                <Icon name="chevron" size={16}/>
            </div>
            <div className="dashboard-prospect-meta">
                <span><small>Status</small><StatusBadge status={prospect.status} label={prospect.status_label}/></span>
                <span><small>Skor</small><strong>{prospect.total_score}/9</strong></span>
                <span><small>Potensi</small><strong>{money(prospect.estimated_deal_value)}</strong></span>
            </div>
        </Link>
    );
}

function CommissionSnapshot({ summary }) {
    return (
        <section className="panel commission-snapshot">
            <SectionHeader
                eyebrow="Commission"
                title="Komisi saya"
                description="Berbasis pembayaran client terverifikasi"
                action={<Link href="/finance/commissions" className="text-link">Ledger <Icon name="chevron" size={14}/></Link>}
            />
            <div className="commission-snapshot-grid">
                <div><small>Potential</small><strong>Rp{compactMoney(summary?.potential_commission || 0)}</strong></div>
                <div><small>Earned</small><strong>Rp{compactMoney(summary?.earned_commission || 0)}</strong></div>
                <div><small>Paid</small><strong>Rp{compactMoney(summary?.commission_paid || 0)}</strong></div>
            </div>
        </section>
    );
}

function greeting() {
    const hour = new Date().getHours();
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';
    return 'Selamat malam';
}

export default function Dashboard({ stats, pipeline, followUps, topProspects, salesPerformance, teamPerformance, financeSummary }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const firstName = user.name?.split(' ')[0] || user.name;
    const overdueCount = financeSummary?.overdue_count || 0;

    return (
        <AppLayout
            title={`${greeting()}, ${firstName}`}
            subtitle={`${stats.need_followup || 0} follow-up perlu perhatian · ${stats.won_deals || 0} deal won`}
            action={<Link href="/prospects/create" className="btn btn-primary premium-primary-action"><Icon name="plus" size={16}/>Prospek baru</Link>}
        >
            <Head title="Dashboard"/>

            <section className="dashboard-primary-metrics">
                <MetricCard
                    label="Pipeline"
                    value={`Rp${compactMoney(stats.pipeline_value || 0)}`}
                    helper="Open pipeline"
                    icon="pipeline"
                    accent="violet"
                    featured
                    href="/pipeline"
                />
                <MetricCard
                    label="Deal Won"
                    value={`Rp${compactMoney(stats.actual_deal_value || 0)}`}
                    helper={`${stats.won_deals || 0} deal`}
                    icon="check"
                    accent="green"
                    featured
                    href="/deals"
                />
                <MetricCard
                    label="Collected"
                    value={`Rp${compactMoney(financeSummary?.paid_by_client || 0)}`}
                    helper="Net payment"
                    icon="check"
                    accent="blue"
                    featured
                    href="/finance"
                />
                <MetricCard
                    label="Outstanding"
                    value={`Rp${compactMoney(financeSummary?.outstanding || 0)}`}
                    helper={overdueCount ? `${overdueCount} overdue` : 'Receivable'}
                    icon="alert"
                    accent={overdueCount ? 'red' : 'amber'}
                    featured
                    href="/finance"
                />
            </section>

            <section className="dashboard-quick-stats" aria-label="Ringkasan aktivitas">
                <QuickStat label="Prospek" value={stats.total || 0} icon="building" tone="blue" href="/prospects"/>
                <QuickStat label="Follow-up" value={stats.need_followup || 0} icon="calendar" tone={(stats.need_followup || 0) > 0 ? 'amber' : 'green'} href="/follow-ups"/>
                <QuickStat label="Opportunity" value={`Rp${compactMoney(stats.opportunity_value || 0)}`} icon="target" tone="violet" href="/opportunities"/>
                <QuickStat label="Quotation" value={`Rp${compactMoney(stats.quotation_value || 0)}`} icon="briefcase" tone="blue" href="/quotations"/>
                <QuickStat label="Conversion" value={`${stats.conversion_rate || 0}%`} icon="pipeline" tone="green" href="/pipeline"/>
                <QuickStat label="Earned Commission" value={`Rp${compactMoney(financeSummary?.earned_commission || 0)}`} icon="target" tone="amber" href="/finance/commissions"/>
            </section>

            <section className="dashboard-command-grid">
                <div className="dashboard-command-main">
                    {!user.is_admin && !user.is_finance && <PerformanceCard performance={salesPerformance}/>} 

                    {user.is_admin && (
                        <section className="panel dashboard-team-panel premium-team-panel">
                            <SectionHeader
                                eyebrow="Team"
                                title="Account Executive"
                                description="Progress target bulan berjalan"
                                action={<Link href="/targets" className="text-link">Target AE <Icon name="chevron" size={14}/></Link>}
                            />
                            {teamPerformance.length ? (
                                <div className="team-performance-list">
                                    {teamPerformance.slice(0, 5).map(({ user: member, performance }) => (
                                        <div className="team-performance-row premium-team-row" key={member.id}>
                                            <Avatar name={member.name}/>
                                            <div className="team-performance-copy">
                                                <div><strong>{member.name}</strong><span>{performance.actual.deals} deal · {money(performance.actual.revenue)}</span></div>
                                                <Progress value={performance.score}/>
                                            </div>
                                            <b>{performance.score}%</b>
                                        </div>
                                    ))}
                                </div>
                            ) : <EmptyState title="Belum ada Account Executive" description="Tambahkan AE untuk mulai mengukur performa."/>}
                        </section>
                    )}

                    <section className="panel premium-funnel-panel">
                        <SectionHeader
                            eyebrow="Pipeline"
                            title="Stage overview"
                            description="Distribusi prospek aktif"
                            action={<Link href="/pipeline" className="text-link">Buka pipeline <Icon name="chevron" size={14}/></Link>}
                        />
                        <div className="premium-funnel-list">
                            {pipeline.slice(0, 9).map((item, index) => (
                                <Link href={`/prospects?status=${item.key}`} className="premium-funnel-stage" key={item.key}>
                                    <span>{String(index + 1).padStart(2, '0')}</span>
                                    <div><strong>{item.count}</strong><small>{item.label}</small></div>
                                    <Icon name="chevron" size={14}/>
                                </Link>
                            ))}
                        </div>
                    </section>
                </div>

                <aside className="dashboard-command-side">
                    <section className="panel sticky-panel premium-followup-panel">
                        <SectionHeader
                            eyebrow="Today"
                            title="Follow-up queue"
                            description="Urut dari yang paling dekat"
                            action={<span className="count-bubble">{followUps.length}</span>}
                        />
                        {followUps.length
                            ? <div className="followup-list premium-followup-list">{followUps.map((prospect) => <FollowUpMiniCard key={prospect.id} prospect={prospect}/>)}</div>
                            : <EmptyState icon="calendar" title="Queue aman" description="Tidak ada follow-up terdekat."/>}
                        <Link href="/follow-ups" className="btn btn-soft btn-block">Buka queue</Link>
                    </section>
                    {!user.is_finance && <CommissionSnapshot summary={financeSummary}/>} 
                </aside>
            </section>

            <section className="panel premium-priority-panel">
                <SectionHeader
                    eyebrow="Priority"
                    title="Prospek yang perlu perhatian"
                    description="Skor dan potensi tertinggi"
                    action={<Link href="/prospects?priority=tinggi" className="text-link">Semua prospek <Icon name="chevron" size={14}/></Link>}
                />

                {topProspects.length ? (
                    <>
                        <div className="clean-table-wrap desktop-only">
                            <table className="clean-table premium-table">
                                <thead><tr><th>Perusahaan</th><th>Owner</th><th>Status</th><th>Skor</th><th>Potensi</th><th/></tr></thead>
                                <tbody>
                                    {topProspects.map((prospect) => (
                                        <tr key={prospect.id}>
                                            <td>
                                                <div className="company-cell">
                                                    <span className="company-avatar">{prospect.company_name?.[0]}</span>
                                                    <div><strong>{prospect.company_name}</strong><small>{[prospect.city, prospect.service].filter(Boolean).join(' · ') || 'Belum dilengkapi'}</small></div>
                                                </div>
                                            </td>
                                            <td>{prospect.assigned_user?.name || 'Belum diassign'}</td>
                                            <td><StatusBadge status={prospect.status} label={prospect.status_label}/></td>
                                            <td><span className="score-pill">{prospect.total_score}/9</span></td>
                                            <td><strong>{money(prospect.estimated_deal_value)}</strong></td>
                                            <td><Link className="icon-button" href={`/prospects/${prospect.id}`}><Icon name="chevron" size={16}/></Link></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="mobile-only dashboard-prospect-mobile-list">
                            {topProspects.map((prospect) => <MobileProspectCard key={prospect.id} prospect={prospect}/>) }
                        </div>
                    </>
                ) : <EmptyState title="Belum ada prospek prioritas" description="Prospek dengan skor terbaik akan tampil di sini."/>}
            </section>
        </AppLayout>
    );
}
