import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { MetricCard, PriorityBadge, Progress, StatusBadge, Avatar, EmptyState } from '../../Components/Ui';
import { compactMoney, dateTime, isDue, money } from '../../Utils/format';

function PerformanceCard({ performance, name, compact=false }) {
    if (!performance) return null;
    const items = [
        ['Dihubungi','contacted'],['Meeting','meetings'],['Proposal','proposals'],['Deal','deals'],['Revenue','revenue'],
    ];
    return <article className={`panel target-overview ${compact?'target-compact':''}`}>
        <div className="panel-head"><div><span className="eyebrow">Target bulan ini</span><h3>{name || performance.period.label}</h3></div><div className="score-ring" style={{'--score':`${performance.score * 3.6}deg`}}><span>{performance.score}%</span></div></div>
        <div className="target-lines">
            {items.map(([label,key])=><div className="target-line" key={key}><div className="target-line-copy"><span>{label}</span><strong>{key==='revenue'?`${compactMoney(performance.actual[key])} / ${compactMoney(performance.goals[key])}`:`${performance.actual[key]} / ${performance.goals[key]}`}</strong></div><Progress value={performance.progress[key]}/></div>)}
        </div>
        {!compact && <div className="target-footer"><span><b>{performance.actual.active_companies}</b> perusahaan aktif di-handle</span><span className={performance.actual.overdue_followups>0?'text-danger':''}><b>{performance.actual.overdue_followups}</b> follow-up overdue</span><Link href="/targets">Lihat target <Icon name="chevron" size={15}/></Link></div>}
    </article>;
}

export default function Dashboard({ stats, pipeline, followUps, topProspects, salesPerformance, teamPerformance }) {
    const { auth } = usePage().props;
    const user=auth.user;
    return <AppLayout title={`Halo, ${user.name.split(' ')[0]}`} subtitle="Fokus pada prospek yang paling dekat ke percakapan dan revenue." action={<Link href="/prospects/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah Prospek</Link>}>
        <Head title="Dashboard"/>
        <section className="metric-grid">
            <MetricCard label="Total Prospek" value={stats.total} helper="database yang dapat Anda akses" icon="building"/>
            <MetricCard label="Prioritas Tinggi" value={stats.priority_high} helper="layak disentuh lebih dulu" icon="target" accent="red"/>
            <MetricCard label="Perlu Follow-up" value={stats.need_followup} helper="jatuh tempo sampai hari ini" icon="calendar" accent="amber"/>
            <MetricCard label="Meeting → Negosiasi" value={stats.meeting_plus} helper="prospek tengah–akhir funnel" icon="briefcase" accent="blue"/>
            <MetricCard label="Deal" value={stats.deals} helper="prospek berhasil ditutup" icon="check" accent="green"/>
            <MetricCard label="Nilai Pipeline" value={`Rp${compactMoney(stats.pipeline_value)}`} helper="estimasi potensi open pipeline" icon="target" accent="violet"/>
        </section>

        <section className="dashboard-layout">
            <div className="dashboard-main">
                {!user.is_admin && <PerformanceCard performance={salesPerformance}/>} 
                {user.is_admin && <section className="panel"><div className="panel-head"><div><span className="eyebrow">Team performance</span><h3>Progress Account Executive bulan ini</h3></div><Link href="/targets" className="text-link">Kelola target AE <Icon name="chevron" size={15}/></Link></div>
                    {teamPerformance.length ? <div className="team-performance-list">{teamPerformance.slice(0,5).map(({user:u,performance})=><div className="team-performance-row" key={u.id}><Avatar name={u.name}/><div className="team-performance-copy"><div><strong>{u.name}</strong><span>{performance.actual.deals} deal · {money(performance.actual.revenue)}</span></div><Progress value={performance.score}/></div><b>{performance.score}%</b></div>)}</div>:<EmptyState title="Belum ada Account Executive" description="Tambahkan Account Executive untuk mulai mengukur target."/>}
                </section>}

                <section className="panel"><div className="panel-head"><div><span className="eyebrow">Funnel</span><h3>Pergerakan pipeline</h3></div><Link href="/pipeline" className="text-link">Buka pipeline <Icon name="chevron" size={15}/></Link></div>
                    <div className="funnel-strip">{pipeline.slice(0,9).map((item,i)=><div className="funnel-item" key={item.key}><div className="funnel-count">{item.count}</div><span>{item.label}</span>{i<8&&<Icon name="chevron" size={15}/>}</div>)}</div>
                </section>

                <section className="panel"><div className="panel-head"><div><span className="eyebrow">Prioritas</span><h3>Prospek terbaik untuk ditindaklanjuti</h3></div><Link href="/prospects?priority=tinggi" className="text-link">Lihat semua <Icon name="chevron" size={15}/></Link></div>
                    <div className="clean-table-wrap"><table className="clean-table"><thead><tr><th>Perusahaan</th><th>Account Executive</th><th>Status</th><th>Skor</th><th>Potensi</th><th></th></tr></thead><tbody>{topProspects.map((p)=><tr key={p.id}><td><div className="company-cell"><span className="company-avatar">{p.company_name?.[0]}</span><div><strong>{p.company_name}</strong><small>{[p.city,p.service].filter(Boolean).join(' · ')||'Belum dilengkapi'}</small></div></div></td><td>{p.assigned_user?.name||'Belum diassign'}</td><td><StatusBadge status={p.status} label={p.status_label}/></td><td><span className="score-pill">{p.total_score}/9</span></td><td>{money(p.estimated_deal_value)}</td><td><Link className="icon-button" href={`/prospects/${p.id}`}><Icon name="chevron" size={17}/></Link></td></tr>)}</tbody></table></div>
                </section>
            </div>

            <aside className="dashboard-side">
                <section className="panel sticky-panel"><div className="panel-head"><div><span className="eyebrow">Account Executive queue</span><h3>Follow-up terdekat</h3></div><span className="count-bubble">{followUps.length}</span></div>
                    {followUps.length?<div className="followup-list">{followUps.map((p)=><Link href={`/prospects/${p.id}`} className="followup-item" key={p.id}><span className={`followup-dot ${isDue(p.next_follow_up_at)?'overdue':''}`}/><div><strong>{p.company_name}</strong><span>{dateTime(p.next_follow_up_at)}</span></div><PriorityBadge priority={p.priority}/></Link>)}</div>:<EmptyState icon="calendar" title="Follow-up aman" description="Tidak ada follow-up jatuh tempo dalam daftar ini."/>}
                    <Link href="/follow-ups" className="btn btn-soft btn-block">Buka Follow Up Queue</Link>
                </section>
            </aside>
        </section>
    </AppLayout>;
}
