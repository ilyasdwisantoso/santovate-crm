import Icon from '../../../Components/Icon';

const kpis = [
    {
        icon: 'users',
        tone: 'blue',
        label: 'Prospects',
        value: '1,248',
        delta: '+12%',
        note: 'New this month',
    },
    {
        icon: 'calendar',
        tone: 'rose',
        label: 'Follow-up Queue',
        value: '320',
        delta: '+8%',
        note: 'Pending follow-ups',
    },
    {
        icon: 'pipeline',
        tone: 'blue',
        label: 'Pipeline Value',
        value: 'Rp480M',
        delta: '+24%',
        note: 'Across 36 deals',
    },
    {
        icon: 'target',
        tone: 'amber',
        label: 'Deals Won',
        value: '87',
        delta: '+35%',
        note: 'This quarter',
    },
];

const stages = [
    { label: 'Lead', height: 44, tone: 'blue' },
    { label: 'Contacted', height: 61, tone: 'blue' },
    { label: 'Qualified', height: 78, tone: 'blue' },
    { label: 'Proposal', height: 66, tone: 'blue' },
    { label: 'Negotiation', height: 52, tone: 'blue' },
    { label: 'Won', height: 87, tone: 'green' },
];

const navigation = [
    ['home', 'Dashboard'],
    ['building', 'Prospects'],
    ['users', 'Companies'],
    ['pipeline', 'Deals'],
    ['check', 'Tasks'],
    ['whatsapp', 'Follow-up'],
    ['target', 'Reports'],
];

export default function HeroDashboardMockup() {
    return (
        <div className="svdash" aria-label="Santovate CRM dashboard preview">
            <aside className="svdash-sidebar">
                <div className="svdash-brand">
                    <span>S</span>
                    <strong>Santovate</strong>
                    <small>CRM</small>
                </div>

                <nav className="svdash-nav" aria-label="Dashboard preview navigation">
                    {navigation.map(([icon, label], index) => (
                        <span key={label} className={index === 0 ? 'active' : ''}>
                            <Icon name={icon} size={15}/>
                            <b>{label}</b>
                        </span>
                    ))}
                </nav>

                <div className="svdash-sidebar-note">
                    <small>Your growth partner in B2B sales.</small>
                    <i/>
                </div>
            </aside>

            <main className="svdash-main">
                <header className="svdash-topbar">
                    <div className="svdash-search">
                        <Icon name="search" size={13}/>
                        <small>Search prospects, companies, or deals...</small>
                    </div>

                    <div className="svdash-topbar-user">
                        <span className="svdash-bell">
                            <Icon name="bell" size={15}/>
                            <i/>
                        </span>
                        <span className="svdash-user-avatar">AC</span>
                        <span>
                            <strong>Alex Carter</strong>
                            <small>Sales Manager</small>
                        </span>
                        <b>⌄</b>
                    </div>
                </header>

                <div className="svdash-heading">
                    <div>
                        <h3>Good morning, Alex <span>👋</span></h3>
                        <p>Here&apos;s what&apos;s happening with your sales today.</p>
                    </div>

                    <div className="svdash-date">
                        <Icon name="calendar" size={14}/>
                        <span>Sep 14, 2026</span>
                    </div>
                </div>

                <section className="svdash-kpis">
                    {kpis.map((item) => (
                        <article key={item.label}>
                            <div className="svdash-kpi-label">
                                <span className={`svdash-kpi-icon tone-${item.tone}`}>
                                    <Icon name={item.icon} size={13}/>
                                </span>
                                <small>{item.label}</small>
                            </div>

                            <div className="svdash-kpi-value">
                                <strong>{item.value}</strong>
                                <em className={item.tone === 'rose' ? 'negative' : ''}>
                                    {item.delta}
                                </em>
                            </div>

                            <p>{item.note}</p>
                        </article>
                    ))}
                </section>

                <section className="svdash-workspace">
                    <article className="svdash-pipeline-card">
                        <header>
                            <strong>Sales Pipeline</strong>
                            <button type="button">This Quarter <span>⌄</span></button>
                        </header>

                        <div className="svdash-chart-layout">
                            <div className="svdash-axis">
                                <span>250</span>
                                <span>200</span>
                                <span>150</span>
                                <span>100</span>
                                <span>50</span>
                                <span>0</span>
                            </div>

                            <div className="svdash-chart">
                                <div className="svdash-grid-lines" aria-hidden="true">
                                    <i/><i/><i/><i/><i/>
                                </div>

                                {stages.map((stage, index) => (
                                    <div className="svdash-bar-slot" key={stage.label}>
                                        <div
                                            className={`svdash-bar tone-${stage.tone}`}
                                            style={{
                                                '--bar-height': `${stage.height}%`,
                                                '--bar-delay': `${320 + index * 90}ms`,
                                            }}
                                        />
                                        <small>{stage.label}</small>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </article>

                    <article className="svdash-target-card">
                        <header>
                            <strong>Target Progress</strong>
                        </header>

                        <small>Quarterly Revenue Target</small>

                        <div className="svdash-target-value">
                            <strong>Rp480M</strong>
                            <span>/ Rp600M</span>
                        </div>

                        <div className="svdash-progress-row">
                            <div className="svdash-progress"><i/></div>
                            <strong>80%</strong>
                        </div>

                        <div className="svdash-ahead">
                            <span>↗</span>
                            <p>
                                You&apos;re <strong>20% ahead</strong>
                                <small>of last quarter</small>
                            </p>
                            <b>›</b>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    );
}
