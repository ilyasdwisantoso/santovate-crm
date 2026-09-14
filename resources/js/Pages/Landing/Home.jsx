import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Icon from '../../Components/Icon';
import LandingNav from './components/LandingNav';
import LandingFooter from './components/LandingFooter';

const benefits = [
    {
        title: 'Easy to Set Up',
        copy: 'Mulai dari database prospek, assignment AE, sampai pipeline tanpa workflow yang rumit.',
        icon: 'spark',
        tone: 'lime',
    },
    {
        title: 'Fast & Lightweight',
        copy: 'Interface cepat dan fokus pada pekerjaan sales yang benar-benar dilakukan setiap hari.',
        icon: 'target',
        tone: 'violet',
    },
    {
        title: 'Insight-Driven Reporting',
        copy: 'Pantau target, aktivitas, deal, dan revenue dari dashboard yang mudah dibaca.',
        icon: 'pipeline',
        tone: 'orange',
    },
    {
        title: 'Secure & Scalable',
        copy: 'Role-based access dan struktur data yang siap dikembangkan mengikuti pertumbuhan tim.',
        icon: 'check',
        tone: 'blue',
    },
];

const industries = [
    {
        n: '01',
        title: 'Logistics & Freight',
        copy: 'Kelola prospek, quotation follow-up, account ownership, dan relationship B2B dalam satu alur.',
        tag: 'Logistics',
    },
    {
        n: '02',
        title: 'Software & Agency',
        copy: 'Dari discovery sampai proposal, negotiation, dan project won tanpa kehilangan konteks komunikasi.',
        tag: 'Services',
    },
    {
        n: '03',
        title: 'Distributor & B2B Sales',
        copy: 'Atur account list, quotation, territory, follow-up, dan peluang repeat order secara terpusat.',
        tag: 'B2B',
    },
];

const testimonials = [
    {
        n: '01',
        metric: 'Clear',
        metricLabel: 'sales ownership',
        quote: 'Setiap prospect punya owner, status, histori aktivitas, dan next action yang jelas. Meeting pipeline jadi jauh lebih terarah.',
        person: 'Sales Team',
        role: 'B2B Operations',
    },
    {
        n: '02',
        metric: 'H-3',
        metricLabel: 'feedback discipline',
        quote: 'Queue follow-up membantu AE mengetahui mana lead yang harus disentuh hari ini, bukan menunggu sampai peluangnya dingin.',
        person: 'Account Executive',
        role: 'Santovate CRM',
    },
    {
        n: '03',
        metric: '1',
        metricLabel: 'central workspace',
        quote: 'Prospek, follow-up, pipeline, target dan performance berada di satu tempat sehingga tim tidak bergantung pada spreadsheet terpisah.',
        person: 'Sales Leader',
        role: 'Revenue Team',
    },
];

const faqs = [
    {
        q: 'Apakah Santovate CRM bisa digunakan bisnis selain Santovate?',
        a: 'Ya. Santovate CRM dirancang dari workflow penjualan B2B yang dapat disesuaikan untuk logistics, software house, agency, distributor, consulting, property, dan bisnis dengan tim sales atau Account Executive.',
    },
    {
        q: 'Apakah CRM ini nyaman digunakan dari HP?',
        a: 'Ya. Dashboard, prospek, follow-up, pipeline, target, profile, dan navigasi utama dibuat responsive untuk penggunaan desktop maupun mobile.',
    },
    {
        q: 'Bagaimana mekanisme follow-up WhatsApp?',
        a: 'Sales dapat memilih template, mereview dan mengedit isi pesan terlebih dahulu, lalu membuka WhatsApp. Status sudah dikirim dicatat secara terpisah agar histori tetap akurat.',
    },
    {
        q: 'Apakah database prospek bisa di-import?',
        a: 'Ya. Admin dapat mengunggah file XLSX, XLS, atau CSV, melakukan preview dan validasi, lalu meng-assign prospek ke Account Executive tujuan.',
    },
    {
        q: 'Apakah pipeline dan field dapat disesuaikan?',
        a: 'Core CRM dibuat untuk workflow B2B dan dapat dikembangkan dengan pipeline, terminology, custom fields, serta konfigurasi industri sesuai scope implementasi.',
    },
];

function ProductWindow({ children, className = '' }) {
    return (
        <div className={`fl-product-window ${className}`}>
            <div className="fl-window-bar">
                <div className="fl-window-dots"><i/><i/><i/></div>
                <div className="fl-window-address">crm.santovate.com</div>
                <div className="fl-window-action"><Icon name="more" size={16}/></div>
            </div>
            {children}
        </div>
    );
}

function Avatar({ label, tone = 'blue' }) {
    return <span className={`fl-avatar tone-${tone}`}>{label}</span>;
}

function HeroDashboard() {
    const stages = [
        ['Lead Baru', '28', 'blue'],
        ['Contacted', '17', 'lime'],
        ['Meeting', '9', 'orange'],
        ['Proposal', '6', 'violet'],
        ['Deal', '3', 'dark'],
    ];

    return (
        <ProductWindow className="fl-hero-dashboard">
            <div className="fl-dashboard-shell">
                <aside className="fl-dashboard-side">
                    <div className="fl-mini-brand"><span>S</span><b>Santovate</b></div>
                    <div className="fl-side-links">
                        <i className="active"><Icon name="home" size={17}/><span>Dashboard</span></i>
                        <i><Icon name="building" size={17}/><span>Prospek</span></i>
                        <i><Icon name="whatsapp" size={17}/><span>Follow Up</span></i>
                        <i><Icon name="pipeline" size={17}/><span>Pipeline</span></i>
                        <i><Icon name="target" size={17}/><span>Target AE</span></i>
                    </div>
                    <div className="fl-side-user">
                        <Avatar label="SA" tone="lime"/>
                        <span><b>Santovate Admin</b><small>Administrator</small></span>
                    </div>
                </aside>

                <section className="fl-dashboard-content">
                    <div className="fl-dashboard-top">
                        <div>
                            <small>Good morning</small>
                            <h3>Sales overview</h3>
                        </div>
                        <button><Icon name="plus" size={16}/> Prospect</button>
                    </div>

                    <div className="fl-metric-row">
                        <article><small>Total Prospect</small><strong>128</strong><em>+12 this month</em></article>
                        <article><small>Need Follow-up</small><strong>24</strong><em>Priority today</em></article>
                        <article><small>Meeting</small><strong>18</strong><em>14% conversion</em></article>
                        <article><small>Pipeline Value</small><strong>Rp 420M</strong><em>Active opportunities</em></article>
                    </div>

                    <div className="fl-dashboard-grid">
                        <article className="fl-chart-card">
                            <div className="fl-card-head"><b>Pipeline performance</b><span>30 days</span></div>
                            <div className="fl-chart-visual">
                                <div className="fl-chart-y"><span>100</span><span>75</span><span>50</span><span>25</span></div>
                                <div className="fl-chart-area">
                                    <div className="fl-gridline g1"/><div className="fl-gridline g2"/><div className="fl-gridline g3"/>
                                    <svg viewBox="0 0 500 180" preserveAspectRatio="none" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#7c9c39" stopOpacity=".22"/>
                                                <stop offset="100%" stopColor="#7c9c39" stopOpacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M0 150 C55 138,78 105,120 116 C170 128,190 72,236 88 C281 104,310 48,355 62 C407 78,442 26,500 34 L500 180 L0 180 Z" fill="url(#areaFill)"/>
                                        <path d="M0 150 C55 138,78 105,120 116 C170 128,190 72,236 88 C281 104,310 48,355 62 C407 78,442 26,500 34" fill="none" stroke="#6e8f2e" strokeWidth="4" strokeLinecap="round"/>
                                    </svg>
                                </div>
                            </div>
                        </article>

                        <article className="fl-follow-card">
                            <div className="fl-card-head"><b>Follow-up queue</b><span>Today</span></div>
                            {[
                                ['PT Arunika Logistik','Needs feedback','AM','blue'],
                                ['Nusantara Digital','Follow-up today','RD','orange'],
                                ['Atlas Cargo','Customer replied','YS','lime'],
                            ].map(([name,status,initial,tone]) => (
                                <div className="fl-follow-item" key={name}>
                                    <Avatar label={initial} tone={tone}/>
                                    <span><b>{name}</b><small>{status}</small></span>
                                    <i/>
                                </div>
                            ))}
                        </article>
                    </div>

                    <div className="fl-pipeline-strip">
                        {stages.map(([name,count,tone]) => (
                            <article className={`tone-${tone}`} key={name}>
                                <span>{name}</span><strong>{count}</strong>
                            </article>
                        ))}
                    </div>
                </section>
            </div>
        </ProductWindow>
    );
}

function PipelineMockup() {
    const columns = [
        ['Lead', '8', [['Atlas Cargo','Rp 28M','AC'],['Nusa Group','Rp 15M','NG']]],
        ['Contacted', '5', [['Primex','Rp 42M','PX'],['Vistara','Rp 19M','VS']]],
        ['Proposal', '3', [['Orion B2B','Rp 68M','OB']]],
        ['Negotiation', '2', [['Delta Logistik','Rp 95M','DL']]],
    ];

    return (
        <div className="fl-mockup-canvas fl-pipeline-mock">
            <div className="fl-mock-head"><span>Sales pipeline</span><button><Icon name="plus" size={14}/> Deal</button></div>
            <div className="fl-kanban">
                {columns.map(([title,count,cards], colIndex) => (
                    <div className="fl-kanban-column" key={title}>
                        <div className="fl-kanban-title"><span><i className={`dot d${colIndex + 1}`}/>{title}</span><b>{count}</b></div>
                        {cards.map(([name,value,avatar], idx) => (
                            <div className="fl-deal-card" key={name}>
                                <div><Avatar label={avatar} tone={['blue','orange','lime','violet'][colIndex]}/><i>•••</i></div>
                                <strong>{name}</strong>
                                <small>{value}</small>
                                <em>{idx === 0 ? 'Follow-up today' : 'Active opportunity'}</em>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

function FollowupMockup() {
    return (
        <div className="fl-mockup-canvas fl-followup-mock">
            <div className="fl-followup-left">
                <div className="fl-mock-head"><span>Follow-up workspace</span><b>24</b></div>
                {[
                    ['Atlas Cargo','Need feedback · H-3','AC','violet'],
                    ['Prima Niaga','Customer replied','PN','lime'],
                    ['Lumina Tech','Scheduled today','LT','orange'],
                    ['Nova Freight','Need reply','NF','blue'],
                ].map(([name,status,initial,tone], idx) => (
                    <div className={`fl-message-row ${idx === 0 ? 'active' : ''}`} key={name}>
                        <Avatar label={initial} tone={tone}/>
                        <span><strong>{name}</strong><small>{status}</small></span>
                        <i>{idx === 0 ? '3m' : `${idx + 1}h`}</i>
                    </div>
                ))}
            </div>
            <div className="fl-composer">
                <div className="fl-composer-person">
                    <Avatar label="AC" tone="violet"/>
                    <span><strong>Atlas Cargo</strong><small>Prospect · Logistics</small></span>
                    <em>Review first</em>
                </div>
                <div className="fl-message-bubble">Halo Bapak/Ibu, saya ingin follow-up terkait kebutuhan pengiriman yang sempat kita bahas sebelumnya.</div>
                <div className="fl-message-bubble second">Apakah ada informasi yang bisa kami bantu lengkapi hari ini?</div>
                <div className="fl-composer-actions">
                    <button className="secondary"><Icon name="edit" size={15}/> Edit template</button>
                    <button className="primary"><Icon name="whatsapp" size={15}/> Open WhatsApp</button>
                </div>
            </div>
        </div>
    );
}

function ReportMockup() {
    return (
        <div className="fl-mockup-canvas fl-report-mock">
            <div className="fl-report-head">
                <div><small>Revenue intelligence</small><strong>Team performance</strong></div>
                <span>Sep 2026</span>
            </div>
            <div className="fl-report-numbers">
                <article><small>Revenue</small><strong>Rp 184M</strong><em>+18.4%</em></article>
                <article><small>Win rate</small><strong>31.8%</strong><em>+4.2%</em></article>
                <article><small>Deals won</small><strong>12</strong><em>+3</em></article>
            </div>
            <div className="fl-report-main">
                <div className="fl-bars">
                    {[46,62,51,72,58,82,69,94].map((h,i) => <i key={i} style={{height:`${h}%`}}><span/></i>)}
                </div>
                <div className="fl-report-people">
                    {[
                        ['Ahmad Mazkur','82%','AM'],
                        ['Sales Executive','71%','SE'],
                        ['Account Executive','64%','AE'],
                    ].map(([name,score,initial],i) => (
                        <div key={name}>
                            <Avatar label={initial} tone={['lime','blue','orange'][i]}/>
                            <span><b>{name}</b><small>Target progress</small></span>
                            <strong>{score}</strong>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function IntegrationIcon({ icon, label, tone }) {
    return (
        <div className={`fl-integration-icon tone-${tone}`}>
            <span><Icon name={icon} size={22}/></span>
            <b>{label}</b>
        </div>
    );
}

function SectionLabel({ children }) {
    return <div className="fl-section-label">/ {children}</div>;
}

export default function Home() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const [faqOpen, setFaqOpen] = useState(0);

    const ctaHref = user ? '/dashboard' : '/login';
    const ctaText = user ? 'Open Dashboard' : 'Request Demo';

    const logos = useMemo(() => ['B2B SALES', 'LOGISTICS', 'SOFTWARE', 'AGENCY', 'DISTRIBUTOR', 'CONSULTING'], []);

    return (
        <div className="fl-page" id="top">
            <Head title="Santovate CRM — B2B Sales Workspace"/>
            <LandingNav/>

            <main>
                <section className="fl-hero">
                    <div className="fl-container">
                        <div className="fl-hero-copy">
                            <SectionLabel>Welcome to Santovate CRM</SectionLabel>
                            <h1>B2B Sales CRM<br/>That Powers <em>Real Growth</em></h1>
                            <p>From prospect management to disciplined follow-up, Santovate CRM helps your team stay focused, move faster, and turn pipeline into measurable revenue.</p>
                            <div className="fl-button-group">
                                <Link href={ctaHref} className="fl-btn primary">{ctaText}<Icon name="chevron" size={17}/></Link>
                                <a href="#features" className="fl-btn secondary">Explore Features</a>
                            </div>
                            <div className="fl-hero-review">
                                <div className="fl-avatar-stack">
                                    <Avatar label="AE" tone="blue"/>
                                    <Avatar label="SA" tone="lime"/>
                                    <Avatar label="AM" tone="orange"/>
                                </div>
                                <div className="fl-review-stars"><span>★★★★★</span><b>4.8/5</b><small>Built for modern B2B teams</small></div>
                            </div>
                        </div>

                        <div className="fl-hero-product">
                            <span className="fl-hero-shape shape-a"/>
                            <span className="fl-hero-shape shape-b"/>
                            <HeroDashboard/>
                            <div className="fl-float-card fc-one"><span>+18%</span><small>Pipeline growth</small></div>
                            <div className="fl-float-card fc-two"><Icon name="check" size={16}/><span>Follow-up done</span></div>
                        </div>
                    </div>
                </section>

                <section className="fl-logo-section">
                    <div className="fl-container">
                        <p>Trusted workflow for fast-moving B2B sales teams</p>
                        <div className="fl-logo-row">
                            {logos.map((logo) => <span key={logo}>{logo}</span>)}
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-benefits" id="benefits">
                    <div className="fl-container">
                        <SectionLabel>Benefits</SectionLabel>
                        <div className="fl-section-heading split">
                            <h2>Why B2B Sales Teams<br/>Choose Santovate</h2>
                            <p>Simple enough for reps to use every day, structured enough for leaders to see exactly what is happening across the pipeline.</p>
                        </div>

                        <div className="fl-benefit-grid">
                            <article className="fl-benefit-feature">
                                <div className="fl-benefit-visual">
                                    <div className="fl-task-stack">
                                        <div className="fl-task-card t1"><Avatar label="AC" tone="blue"/><span><b>Atlas Cargo</b><small>Follow-up today</small></span><i><Icon name="check" size={15}/></i></div>
                                        <div className="fl-task-card t2"><Avatar label="PN" tone="orange"/><span><b>Prima Niaga</b><small>Proposal sent</small></span><i><Icon name="check" size={15}/></i></div>
                                        <div className="fl-task-card t3"><Avatar label="LT" tone="lime"/><span><b>Lumina Tech</b><small>Meeting scheduled</small></span><i><Icon name="calendar" size={15}/></i></div>
                                    </div>
                                    <div className="fl-big-check"><Icon name="check" size={34}/></div>
                                </div>
                                <div>
                                    <span>Rep-first workflow</span>
                                    <h3>Sales knows exactly what to do next.</h3>
                                </div>
                            </article>

                            <div className="fl-benefit-list">
                                {benefits.map((item) => (
                                    <article className={`fl-benefit-item tone-${item.tone}`} key={item.title}>
                                        <span className="fl-benefit-icon"><Icon name={item.icon} size={21}/></span>
                                        <div><h3>{item.title}</h3><p>{item.copy}</p></div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-features" id="features">
                    <div className="fl-container">
                        <SectionLabel>Features</SectionLabel>
                        <div className="fl-section-heading split">
                            <h2>All Your Sales Tools,<br/>In One Intuitive Platform</h2>
                            <div>
                                <p>Setiap fitur dirancang untuk mengurangi pekerjaan manual, membuat follow-up lebih konsisten, dan memberikan visibility penuh ke pipeline.</p>
                                <a href="#pricing" className="fl-inline-link">All Features <Icon name="chevron" size={16}/></a>
                            </div>
                        </div>

                        <div className="fl-feature-row">
                            <div className="fl-feature-copy">
                                <span className="fl-feature-number">/ 01</span>
                                <h3>Smarter Pipelines</h3>
                                <p>Kelola tahapan penjualan secara visual. Setiap prospect membawa owner, nilai peluang, status, dan next action sehingga pipeline selalu actionable.</p>
                                <a href="#industries" className="fl-text-link">Learn More <Icon name="chevron" size={15}/></a>
                            </div>
                            <PipelineMockup/>
                        </div>

                        <div className="fl-feature-row reverse">
                            <FollowupMockup/>
                            <div className="fl-feature-copy">
                                <span className="fl-feature-number">/ 02</span>
                                <h3>Follow-up Workspace</h3>
                                <p>Gunakan queue, H-3 feedback, reminder, dan template WhatsApp yang dapat direview serta diedit sebelum pesan dibuka di WhatsApp.</p>
                                <a href="#workflow" className="fl-text-link">Learn More <Icon name="chevron" size={15}/></a>
                            </div>
                        </div>

                        <div className="fl-feature-row">
                            <div className="fl-feature-copy">
                                <span className="fl-feature-number">/ 03</span>
                                <h3>Forecasting & Reporting</h3>
                                <p>Pantau aktivitas AE, progress target, pipeline value, deal won, dan revenue dengan reporting yang berorientasi tindakan.</p>
                                <a href="#pricing" className="fl-text-link">Learn More <Icon name="chevron" size={15}/></a>
                            </div>
                            <ReportMockup/>
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-why">
                    <div className="fl-container fl-why-grid">
                        <div className="fl-why-copy">
                            <SectionLabel>Why Santovate</SectionLabel>
                            <h2>More Visibility.<br/>Less Friction.<br/>Better Results.</h2>
                            <p>Santovate CRM dibuat untuk tim B2B yang membutuhkan kecepatan, kesederhanaan, dan visibility pipeline tanpa kerumitan CRM tradisional.</p>
                            <div className="fl-why-points">
                                <span><i><Icon name="check" size={14}/></i> Interface yang cepat dan fokus</span>
                                <span><i><Icon name="check" size={14}/></i> Dashboard real-time untuk sales leader</span>
                                <span><i><Icon name="check" size={14}/></i> Follow-up workflow yang benar-benar actionable</span>
                            </div>
                            <Link className="fl-btn light" href={ctaHref}>See Santovate in Action <Icon name="chevron" size={17}/></Link>
                        </div>

                        <div className="fl-why-board">
                            <div className="fl-why-board-head"><span>September performance</span><b>Live</b></div>
                            <div className="fl-why-kpi"><small>Pipeline value</small><strong>Rp 420.000.000</strong><em>+18.4%</em></div>
                            <div className="fl-why-graph">
                                <svg viewBox="0 0 560 220" preserveAspectRatio="none" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="whyFill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stopColor="#c9e268" stopOpacity=".42"/>
                                            <stop offset="100%" stopColor="#c9e268" stopOpacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M0 190 C58 175,80 135,127 146 C179 159,204 104,250 117 C305 133,330 79,376 88 C430 99,470 40,560 48 L560 220 L0 220 Z" fill="url(#whyFill)"/>
                                    <path d="M0 190 C58 175,80 135,127 146 C179 159,204 104,250 117 C305 133,330 79,376 88 C430 99,470 40,560 48" fill="none" stroke="#c9e268" strokeWidth="4" strokeLinecap="round"/>
                                </svg>
                            </div>
                            <div className="fl-why-bottom">
                                <span><b>31.8%</b><small>Win rate</small></span>
                                <span><b>24</b><small>Follow-ups</small></span>
                                <span><b>12</b><small>Deals won</small></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="fl-marquee" aria-hidden="true">
                    <div>
                        <span>Built for B2B Sales</span><i>*</i>
                        <span>Mobile Ready</span><i>*</i>
                        <span>Follow-up Focused</span><i>*</i>
                        <span>Clear Pipeline</span><i>*</i>
                        <span>Built for B2B Sales</span><i>*</i>
                        <span>Mobile Ready</span>
                    </div>
                </section>

                <section className="fl-section fl-industries" id="industries">
                    <div className="fl-container">
                        <SectionLabel>Industries</SectionLabel>
                        <div className="fl-section-heading split">
                            <h2>Made for B2B Teams<br/>Across Industries</h2>
                            <p>Core product yang sama dapat disesuaikan melalui workflow, terminology, pipeline, dan field sesuai cara bisnis Anda menjual.</p>
                        </div>
                        <div className="fl-industry-grid">
                            {industries.map((item, idx) => (
                                <article key={item.title}>
                                    <div className={`fl-industry-art art-${idx + 1}`}>
                                        <span className="fl-industry-tag">{item.tag}</span>
                                        <div className="fl-industry-ui">
                                            <i/><i/><i/>
                                            <b>{item.n}</b>
                                        </div>
                                    </div>
                                    <span className="fl-industry-num">/ {item.n}</span>
                                    <h3>{item.title}</h3>
                                    <p>{item.copy}</p>
                                    <a href="#pricing">Learn More <Icon name="chevron" size={15}/></a>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-testimonials">
                    <div className="fl-container">
                        <SectionLabel>Testimonials</SectionLabel>
                        <div className="fl-section-heading split">
                            <h2>Built Around The Way<br/>B2B Teams Actually Sell</h2>
                            <p>Fokus pada clarity, velocity, dan consistency—tiga hal yang menentukan apakah pipeline bergerak atau hanya menjadi data.</p>
                        </div>
                        <div className="fl-testimonial-grid">
                            {testimonials.map((item, idx) => (
                                <article className={idx === 1 ? 'featured' : ''} key={item.n}>
                                    <span className="fl-test-number">/ {item.n}</span>
                                    <div className="fl-test-metric"><strong>{item.metric}</strong><small>{item.metricLabel}</small></div>
                                    <blockquote>{item.quote}</blockquote>
                                    <div className="fl-test-person">
                                        <Avatar label={item.person.split(' ').map(v=>v[0]).join('').slice(0,2)} tone={['blue','lime','orange'][idx]}/>
                                        <span><b>{item.person}</b><small>{item.role}</small></span>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-integrations" id="workflow">
                    <div className="fl-container fl-integration-grid">
                        <div className="fl-integration-copy">
                            <SectionLabel>Workflow</SectionLabel>
                            <h2>One Workspace.<br/>Every Sales Motion.</h2>
                            <p>Hubungkan aktivitas utama sales dalam satu alur kerja: prospecting, komunikasi, meeting, follow-up, pipeline, dan performance.</p>
                            <a className="fl-btn secondary dark-outline" href="#pricing">Explore Workflow <Icon name="chevron" size={17}/></a>
                        </div>
                        <div className="fl-integration-orbit">
                            <div className="fl-orbit-center"><span>S</span><b>Santovate<br/>CRM</b></div>
                            <IntegrationIcon icon="building" label="Leads" tone="blue"/>
                            <IntegrationIcon icon="whatsapp" label="Follow Up" tone="lime"/>
                            <IntegrationIcon icon="calendar" label="Meeting" tone="orange"/>
                            <IntegrationIcon icon="pipeline" label="Pipeline" tone="violet"/>
                            <IntegrationIcon icon="target" label="Target" tone="dark"/>
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-pricing" id="pricing">
                    <div className="fl-container">
                        <SectionLabel>Pricing</SectionLabel>
                        <div className="fl-section-heading center">
                            <h2>Simple Plans That<br/>Grow With Your Team</h2>
                            <p>Harga implementasi menyesuaikan jumlah user, scope workflow, kebutuhan integrasi, dan tingkat customization.</p>
                        </div>

                        <div className="fl-pricing-grid">
                            <article>
                                <span className="fl-plan-tag">Small Team</span>
                                <h3>Starter</h3>
                                <p>Untuk tim yang ingin beralih dari spreadsheet ke workflow sales yang lebih terstruktur.</p>
                                <div className="fl-price"><strong>Custom</strong><small>implementation</small></div>
                                <Link href={ctaHref} className="fl-btn secondary full">Request Demo</Link>
                                <div className="fl-plan-list">
                                    {['Lead database','Pipeline','Follow-up queue','WhatsApp template','Mobile responsive'].map(x => <span key={x}><i><Icon name="check" size={13}/></i>{x}</span>)}
                                </div>
                            </article>

                            <article className="popular">
                                <span className="fl-plan-tag">Most Popular</span>
                                <h3>Growth</h3>
                                <p>Untuk tim Account Executive yang membutuhkan assignment, target, reporting, dan kontrol follow-up.</p>
                                <div className="fl-price"><strong>Custom</strong><small>per business</small></div>
                                <Link href={ctaHref} className="fl-btn primary full">Talk to Santovate</Link>
                                <div className="fl-plan-list">
                                    {['Everything in Starter','Import & assignment','AE targets','Performance dashboard','Admin team management'].map(x => <span key={x}><i><Icon name="check" size={13}/></i>{x}</span>)}
                                </div>
                            </article>

                            <article>
                                <span className="fl-plan-tag">Business Fit</span>
                                <h3>Custom</h3>
                                <p>Untuk perusahaan yang membutuhkan workflow, field, deployment, atau integrasi khusus.</p>
                                <div className="fl-price"><strong>Tailored</strong><small>scope</small></div>
                                <Link href={ctaHref} className="fl-btn secondary full">Discuss Scope</Link>
                                <div className="fl-plan-list">
                                    {['Everything in Growth','Custom workflow','Industry configuration','Data migration','Integration scope'].map(x => <span key={x}><i><Icon name="check" size={13}/></i>{x}</span>)}
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section className="fl-section fl-faq" id="faq">
                    <div className="fl-container fl-faq-grid">
                        <div className="fl-faq-intro">
                            <SectionLabel>FAQ</SectionLabel>
                            <h2>Everything You Need<br/>to Know—Upfront</h2>
                            <p>Pertanyaan paling umum sebelum tim mulai menggunakan atau mengimplementasikan Santovate CRM.</p>
                            <Link href={ctaHref} className="fl-btn secondary">Request Demo <Icon name="chevron" size={17}/></Link>
                        </div>
                        <div className="fl-faq-list">
                            {faqs.map((item, idx) => (
                                <article className={faqOpen === idx ? 'open' : ''} key={item.q}>
                                    <button onClick={() => setFaqOpen(faqOpen === idx ? -1 : idx)} type="button">
                                        <span>{item.q}</span><i>{faqOpen === idx ? '−' : '+'}</i>
                                    </button>
                                    <div className="fl-faq-answer"><p>{item.a}</p></div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="fl-final-cta">
                    <div className="fl-container">
                        <div className="fl-final-inner">
                            <SectionLabel>Start with Santovate</SectionLabel>
                            <h2>Turn Follow-up Chaos<br/>Into A Clear Sales System.</h2>
                            <p>Bangun workflow penjualan yang lebih konsisten, measurable, dan siap berkembang bersama tim Anda.</p>
                            <div className="fl-button-group">
                                <Link href={ctaHref} className="fl-btn light">{ctaText}<Icon name="chevron" size={17}/></Link>
                                <a href="#features" className="fl-btn ghost-light">Explore Features</a>
                            </div>
                            <div className="fl-final-orb one"/>
                            <div className="fl-final-orb two"/>
                        </div>
                    </div>
                </section>
            </main>

            <LandingFooter/>
        </div>
    );
}
