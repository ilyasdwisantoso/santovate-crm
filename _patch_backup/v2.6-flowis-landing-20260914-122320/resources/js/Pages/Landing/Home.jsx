import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import LandingNav from './components/LandingNav';
import LandingFooter from './components/LandingFooter';

const benefits = [
    {
        icon: 'building',
        tone: 'blue',
        title: 'Lead lebih terorganisir',
        description: 'Simpan perusahaan, PIC, status, scoring, sumber data, dan owner Account Executive dalam satu database yang mudah dicari.',
    },
    {
        icon: 'whatsapp',
        tone: 'green',
        title: 'Follow-up lebih disiplin',
        description: 'Work queue membantu AE melihat siapa yang perlu dibalas, belum memberi feedback, atau sudah jatuh tempo untuk di-follow-up.',
    },
    {
        icon: 'pipeline',
        tone: 'orange',
        title: 'Pipeline lebih terlihat',
        description: 'Pantau pergerakan peluang dari lead sampai deal tanpa kehilangan konteks aktivitas dan next action setiap prospect.',
    },
    {
        icon: 'target',
        tone: 'purple',
        title: 'Performance lebih terukur',
        description: 'Target kontak, meeting, proposal, deal, dan revenue membantu tim fokus pada aktivitas yang benar-benar mendorong hasil.',
    },
];

const featureCards = [
    {
        eyebrow: 'Lead Management',
        title: 'Bangun database prospect yang benar-benar bisa dikerjakan sales.',
        description: 'Bukan sekadar daftar nama perusahaan. Santovate CRM menggabungkan lead scoring, PIC, ownership, follow-up, nilai peluang, sumber data, dan riwayat aktivitas.',
        image: '/assets/landing/crm-contact-management-3d.png',
        imageAlt: 'Ilustrasi 3D contact management Santovate CRM',
        tone: 'blue',
        bullets: ['Lead scoring 0–9', 'Assign Account Executive', 'Import Excel / CSV', 'Activity timeline'],
    },
    {
        eyebrow: 'Follow-up Workspace',
        title: 'Review pesan dulu. Kirim saat sales sudah siap.',
        description: 'Template WhatsApp menjadi draft yang bisa direview dan diedit sebelum membuka WhatsApp. CRM tetap memisahkan tindakan “buka WhatsApp” dan “sudah dikirim” agar histori lebih akurat.',
        image: '/assets/landing/crm-followup-messaging-3d.png',
        imageAlt: 'Ilustrasi 3D follow up messaging Santovate CRM',
        tone: 'green',
        bullets: ['Template pesan editable', 'Queue perlu balas', 'Mandatory feedback H-3', 'Next follow-up reminder'],
    },
    {
        eyebrow: 'Pipeline & Revenue',
        title: 'Lihat proses penjualan sebagai alur, bukan spreadsheet yang terpisah.',
        description: 'Pipeline menyatukan status peluang, nilai deal, owner, dan tahapan sales sehingga meeting tim lebih cepat dan keputusan lebih jelas.',
        image: '/assets/landing/crm-sales-pipeline-3d.png',
        imageAlt: 'Ilustrasi 3D sales pipeline Santovate CRM',
        tone: 'orange',
        bullets: ['Visual pipeline', 'Status progression', 'Deal value', 'Prioritas prospect'],
    },
];

const industries = [
    { icon: 'building', title: 'Logistics & Freight', copy: 'Prospect, quotation follow-up, account ownership, dan relationship B2B.' },
    { icon: 'briefcase', title: 'Software House', copy: 'Lead, discovery, proposal, negotiation, sampai project won.' },
    { icon: 'spark', title: 'Digital Agency', copy: 'Kelola inbound lead, consultation, proposal, retainer, dan pipeline.' },
    { icon: 'users', title: 'Distributor B2B', copy: 'Account list, follow-up quotation, order opportunity, dan sales territory.' },
    { icon: 'target', title: 'Consulting', copy: 'Discovery, meeting, proposal, contract, dan follow-up professional service.' },
    { icon: 'home', title: 'Property Sales', copy: 'Lead qualification, visit, negotiation, dan closing dalam satu workspace.' },
];

const workflow = [
    { step: '01', title: 'Masukkan lead', copy: 'Tambah manual atau import database prospect dari file.' },
    { step: '02', title: 'Assign Account Executive', copy: 'Tentukan ownership supaya setiap lead jelas siapa yang mengerjakan.' },
    { step: '03', title: 'Contact & feedback', copy: 'Catat aktivitas, balasan customer, template pesan, dan next follow-up.' },
    { step: '04', title: 'Gerakkan pipeline', copy: 'Naikkan status berdasarkan progres nyata: meeting, proposal, negosiasi.' },
    { step: '05', title: 'Ukur hasil', copy: 'Pantau target AE, pipeline value, deal, dan revenue dari dashboard.' },
];

const plans = [
    {
        name: 'Starter',
        badge: 'Small team',
        copy: 'Untuk tim kecil yang ingin mengganti spreadsheet dan mulai membangun workflow sales yang rapi.',
        features: ['Lead & contact database', 'Pipeline', 'Follow-up queue', 'WhatsApp templates', 'Mobile responsive'],
    },
    {
        name: 'Growth',
        badge: 'Recommended',
        featured: true,
        copy: 'Untuk tim Account Executive yang membutuhkan assignment, target, reporting, dan workflow follow-up yang lebih disiplin.',
        features: ['Semua fitur Starter', 'Import & assignment', 'Target AE', 'Performance dashboard', 'Admin team management'],
    },
    {
        name: 'Custom',
        badge: 'Business fit',
        copy: 'Untuk bisnis yang membutuhkan penyesuaian field, pipeline, workflow, deployment, atau integrasi sistem lain.',
        features: ['Semua fitur Growth', 'Custom workflow', 'Custom implementation', 'Data migration assistance', 'Integration scope'],
    },
];

const faqs = [
    {
        q: 'Apakah Santovate CRM hanya untuk bisnis Santovate?',
        a: 'Tidak. Core CRM dirancang untuk pola penjualan B2B: lead → contact → follow-up → meeting/proposal → deal. Implementasi dapat disesuaikan dengan istilah, field, dan workflow bisnis client.',
    },
    {
        q: 'Apakah bisa digunakan dari HP?',
        a: 'Ya. Interface CRM dibuat responsive dengan mobile navigation dan card-based views sehingga Account Executive tetap bisa mengakses prospect, follow-up, pipeline, dan profile dari layar kecil.',
    },
    {
        q: 'Apakah WhatsApp terkirim otomatis?',
        a: 'Versi saat ini menggunakan template pesan yang direview dan diedit di CRM sebelum membuka WhatsApp. Pengiriman tetap dilakukan oleh user. Integrasi WhatsApp Business API dapat menjadi scope implementasi terpisah.',
    },
    {
        q: 'Bisa import database prospect dari Excel?',
        a: 'Ya. Admin dapat preview dan validasi file XLSX, XLS, atau CSV sebelum data di-commit, termasuk assignment prospect ke Account Executive.',
    },
    {
        q: 'Apakah pipeline bisa disesuaikan?',
        a: 'Santovate CRM saat ini memiliki pipeline B2B standar. Untuk implementasi client, penyesuaian pipeline dan terminology dapat dimasukkan ke scope konfigurasi/customization sesuai kebutuhan bisnis.',
    },
    {
        q: 'Bagaimana dengan keamanan akses?',
        a: 'Aplikasi menggunakan authentication dan role-based access untuk memisahkan hak Admin dan Account Executive. Kebutuhan security tambahan untuk perusahaan dapat dibahas saat implementasi.',
    },
];

function SectionHeading({ eyebrow, title, description, center = false, inverse = false }) {
    return (
        <div className={`landing-section-heading ${center ? 'is-center' : ''} ${inverse ? 'is-inverse' : ''}`}>
            <span className="landing-kicker">/ {eyebrow}</span>
            <h2>{title}</h2>
            {description && <p>{description}</p>}
        </div>
    );
}

function FeatureVisual({ feature, reverse = false }) {
    return (
        <article className={`landing-feature-showcase ${reverse ? 'is-reverse' : ''} tone-${feature.tone}`}>
            <div className="landing-feature-copy">
                <span className="landing-feature-eyebrow">{feature.eyebrow}</span>
                <h3>{feature.title}</h3>
                <p>{feature.description}</p>
                <div className="landing-feature-bullets">
                    {feature.bullets.map((bullet) => (
                        <span key={bullet}><i><Icon name="check" size={14}/></i>{bullet}</span>
                    ))}
                </div>
                <a href="#pricing" className="landing-text-link">Pelajari implementasi <Icon name="chevron" size={16}/></a>
            </div>
            <div className="landing-feature-visual">
                <span className="landing-visual-orb orb-one"/>
                <span className="landing-visual-orb orb-two"/>
                <img src={feature.image} alt={feature.imageAlt} loading="lazy"/>
            </div>
        </article>
    );
}

function FaqItem({ item, open, onToggle }) {
    return (
        <article className={`landing-faq-item ${open ? 'is-open' : ''}`}>
            <button type="button" onClick={onToggle}>
                <span>{item.q}</span>
                <i>{open ? '−' : '+'}</i>
            </button>
            {open && <div><p>{item.a}</p></div>}
        </article>
    );
}

export default function Home() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const [openFaq, setOpenFaq] = useState(0);

    return (
        <div className="landing-page" id="top">
            <Head title="Santovate CRM — Sales Workspace untuk Tim B2B"/>
            <LandingNav/>

            <main>
                <section className="landing-hero">
                    <div className="landing-hero-grid-bg"/>
                    <span className="landing-hero-glow glow-a"/>
                    <span className="landing-hero-glow glow-b"/>
                    <div className="landing-container landing-hero-inner">
                        <div className="landing-hero-copy">
                            <span className="landing-hero-pill"><i/> Welcome to Santovate CRM</span>
                            <h1>Sales workspace yang membuat <em>follow-up</em> lebih rapi dan pipeline lebih jelas.</h1>
                            <p>Kelola lead, Account Executive, WhatsApp follow-up, pipeline, target, dan performance dalam satu CRM yang dibuat untuk workflow penjualan B2B.</p>
                            <div className="landing-hero-actions">
                                <a className="landing-btn landing-btn-primary landing-btn-lg" href="#pricing">
                                    Request Demo <Icon name="chevron" size={17}/>
                                </a>
                                <a className="landing-btn landing-btn-secondary landing-btn-lg" href="#features">
                                    Explore Features
                                </a>
                            </div>
                            <div className="landing-hero-proof">
                                <div className="landing-avatar-stack" aria-hidden="true">
                                    <span>AE</span><span>SA</span><span>RM</span>
                                </div>
                                <div>
                                    <strong>Built around real sales workflow</strong>
                                    <small>Lead → follow-up → pipeline → deal</small>
                                </div>
                            </div>
                        </div>

                        <div className="landing-hero-stage">
                            <div className="landing-hero-stage-card">
                                <div className="landing-stage-topbar">
                                    <span/><span/><span/>
                                    <b>Santovate CRM</b>
                                </div>
                                <img src="/assets/landing/hero-crm-team-3d.png" alt="Santovate CRM sales team illustration" fetchPriority="high"/>
                                <span className="landing-floating-chip chip-left"><Icon name="whatsapp" size={16}/> Follow-up</span>
                                <span className="landing-floating-chip chip-right"><Icon name="target" size={16}/> Performance</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="landing-trust-strip">
                    <div className="landing-container">
                        <span>Cocok untuk tim B2B yang bergerak cepat</span>
                        <div className="landing-trust-tags">
                            <b>Logistics</b><i/> <b>Software House</b><i/> <b>Agency</b><i/> <b>Distributor</b><i/> <b>Consulting</b>
                        </div>
                    </div>
                </section>

                <section className="landing-section" id="benefits">
                    <div className="landing-container">
                        <SectionHeading
                            eyebrow="Benefits"
                            title="Lebih sedikit chaos. Lebih banyak kejelasan untuk sales."
                            description="Santovate CRM memusatkan informasi dan next action supaya AE tidak bekerja dari chat, spreadsheet, dan catatan yang terpisah-pisah."
                            center
                        />
                        <div className="landing-benefit-grid">
                            {benefits.map((item) => (
                                <article className={`landing-benefit-card tone-${item.tone}`} key={item.title}>
                                    <span className="landing-benefit-icon"><Icon name={item.icon} size={22}/></span>
                                    <h3>{item.title}</h3>
                                    <p>{item.description}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="landing-section landing-section-soft" id="features">
                    <div className="landing-container">
                        <div className="landing-heading-row">
                            <SectionHeading
                                eyebrow="Features"
                                title="Semua alat inti sales, dalam satu workspace yang intuitif."
                                description="Struktur landing page mengikuti ritme SaaS modern: manfaat yang jelas, product showcase yang kuat, lalu workflow dan use case yang mudah dipahami."
                            />
                            <a className="landing-btn landing-btn-secondary" href="#workflow">Lihat workflow</a>
                        </div>
                        <div className="landing-feature-stack">
                            {featureCards.map((feature, index) => (
                                <FeatureVisual key={feature.title} feature={feature} reverse={index % 2 === 1}/>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="landing-section landing-why">
                    <div className="landing-container landing-why-grid">
                        <div>
                            <SectionHeading
                                eyebrow="Why Santovate"
                                title="Visibility lebih tinggi. Friction lebih rendah. Sales lebih fokus."
                                description="CRM dibuat supaya tim benar-benar menggunakannya setiap hari, bukan hanya menjadi tempat menyimpan data setelah meeting selesai."
                                inverse
                            />
                            <div className="landing-why-points">
                                <span><i><Icon name="check" size={15}/></i> Mobile-first interface untuk AE</span>
                                <span><i><Icon name="check" size={15}/></i> Follow-up queue yang actionable</span>
                                <span><i><Icon name="check" size={15}/></i> Reporting yang langsung terkait target</span>
                            </div>
                            <a className="landing-btn landing-btn-light" href="#pricing">See Santovate in Action <Icon name="chevron" size={16}/></a>
                        </div>
                        <div className="landing-why-visual">
                            <img src="/assets/landing/crm-analytics-dashboard-3d.png" alt="Santovate CRM analytics dashboard illustration" loading="lazy"/>
                            <div className="landing-why-stat stat-a"><strong>01</strong><span>Clear ownership</span></div>
                            <div className="landing-why-stat stat-b"><strong>H-3</strong><span>Feedback discipline</span></div>
                        </div>
                    </div>
                </section>

                <section className="landing-section" id="industries">
                    <div className="landing-container">
                        <div className="landing-heading-row">
                            <SectionHeading
                                eyebrow="Industries"
                                title="Dibangun untuk pola kerja sales B2B di berbagai industri."
                                description="Core workflow tetap sama, sementara terminology, data, dan implementasi dapat disesuaikan dengan proses bisnis client."
                            />
                        </div>
                        <div className="landing-industry-grid">
                            {industries.map((industry, index) => (
                                <article className={`landing-industry-card industry-${index + 1}`} key={industry.title}>
                                    <span><Icon name={industry.icon} size={20}/></span>
                                    <h3>{industry.title}</h3>
                                    <p>{industry.copy}</p>
                                    <a href="#pricing">Explore solution <Icon name="chevron" size={14}/></a>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="landing-section landing-workflow-section" id="workflow">
                    <div className="landing-container">
                        <SectionHeading
                            eyebrow="How it works"
                            title="Dari database lead sampai revenue, satu alur kerja."
                            description="Tidak perlu membuat proses sales lebih rumit. Santovate CRM membantu tim menjaga ownership, feedback, dan progres di setiap tahap."
                            center
                        />
                        <div className="landing-workflow">
                            {workflow.map((item, index) => (
                                <article key={item.step}>
                                    <div className="landing-workflow-step"><span>{item.step}</span>{index < workflow.length - 1 && <i/>}</div>
                                    <h3>{item.title}</h3>
                                    <p>{item.copy}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="landing-section landing-mobile-section">
                    <div className="landing-container landing-mobile-grid">
                        <div className="landing-mobile-visual">
                            <span className="landing-mobile-blob"/>
                            <img src="/assets/landing/crm-mobile-app-3d.png" alt="Santovate CRM mobile responsive illustration" loading="lazy"/>
                        </div>
                        <div className="landing-mobile-copy">
                            <SectionHeading
                                eyebrow="Mobile CRM"
                                title="Sales tetap bisa bekerja saat tidak berada di depan laptop."
                                description="Prospect, follow-up, pipeline, target, dan profile dirancang tetap usable di mobile browser dengan bottom navigation dan layout berbasis card."
                            />
                            <div className="landing-mini-grid">
                                <span><Icon name="users" size={18}/><b>Contact</b><small>Prospect & PIC</small></span>
                                <span><Icon name="whatsapp" size={18}/><b>Follow-up</b><small>Daily queue</small></span>
                                <span><Icon name="pipeline" size={18}/><b>Pipeline</b><small>Move stages</small></span>
                                <span><Icon name="target" size={18}/><b>Target</b><small>AE progress</small></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="landing-section landing-ops-section">
                    <div className="landing-container landing-ops-grid">
                        <article className="landing-ops-card ops-automation">
                            <div>
                                <span className="landing-kicker">/ Workflow</span>
                                <h3>Aturan follow-up yang mendorong disiplin kerja.</h3>
                                <p>Queue, due date, no-feedback logic, dan mandatory feedback membantu pekerjaan sales lebih terstruktur tanpa mengklaim otomatisasi yang belum dilakukan sistem.</p>
                            </div>
                            <img src="/assets/landing/crm-workflow-automation-3d.png" alt="CRM workflow illustration" loading="lazy"/>
                        </article>
                        <article className="landing-ops-card ops-security">
                            <div>
                                <span className="landing-kicker">/ Access</span>
                                <h3>Role-based access untuk Admin dan Account Executive.</h3>
                                <p>Authentication, ownership data, dan role membatasi akses sesuai tanggung jawab user dalam CRM.</p>
                            </div>
                            <img src="/assets/landing/crm-secure-cloud-3d.png" alt="CRM security illustration" loading="lazy"/>
                        </article>
                    </div>
                </section>

                <section className="landing-section landing-pricing-section" id="pricing">
                    <div className="landing-container">
                        <SectionHeading
                            eyebrow="Pricing"
                            title="Implementasi yang tumbuh bersama kebutuhan tim."
                            description="Harga final disesuaikan dengan jumlah user, scope konfigurasi, migrasi data, deployment, dan kebutuhan integrasi."
                            center
                        />
                        <div className="landing-pricing-grid">
                            {plans.map((plan) => (
                                <article className={`landing-price-card ${plan.featured ? 'is-featured' : ''}`} key={plan.name}>
                                    <div className="landing-price-head">
                                        <span>{plan.badge}</span>
                                        <h3>{plan.name}</h3>
                                        <strong>Custom Quote</strong>
                                        <p>{plan.copy}</p>
                                    </div>
                                    <a className={`landing-btn ${plan.featured ? 'landing-btn-primary' : 'landing-btn-secondary'} landing-btn-block`} href="https://santovate.com" target="_blank" rel="noreferrer">
                                        Hubungi Santovate <Icon name="chevron" size={15}/>
                                    </a>
                                    <div className="landing-price-features">
                                        <small>What’s included</small>
                                        {plan.features.map((feature) => <span key={feature}><Icon name="check" size={14}/>{feature}</span>)}
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="landing-section" id="faq">
                    <div className="landing-container landing-faq-grid">
                        <div className="landing-faq-side">
                            <SectionHeading
                                eyebrow="FAQ"
                                title="Yang perlu diketahui sebelum implementasi."
                                description="Jawaban dibuat sesuai kemampuan CRM saat ini dan tidak mengklaim fitur enterprise yang belum tersedia."
                            />
                            <img src="/assets/landing/crm-team-avatars-3d.png" alt="Santovate CRM team illustration" loading="lazy"/>
                        </div>
                        <div className="landing-faq-list">
                            {faqs.map((item, index) => (
                                <FaqItem key={item.q} item={item} open={openFaq === index} onToggle={() => setOpenFaq(openFaq === index ? -1 : index)}/>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="landing-final-cta" id="contact">
                    <div className="landing-container">
                        <div className="landing-final-card">
                            <span className="landing-final-glow final-glow-a"/>
                            <span className="landing-final-glow final-glow-b"/>
                            <div>
                                <span className="landing-kicker">/ All-in-one sales workspace</span>
                                <h2>Jangan biarkan peluang hilang hanya karena follow-up terlewat.</h2>
                                <p>Bangun proses sales yang lebih rapi, terlihat, dan bisa diukur bersama Santovate CRM.</p>
                                <div className="landing-hero-actions">
                                    <a className="landing-btn landing-btn-light landing-btn-lg" href="https://santovate.com" target="_blank" rel="noreferrer">Request Demo</a>
                                    <Link className="landing-btn landing-btn-dark-outline landing-btn-lg" href={user ? '/dashboard' : '/login'}>
                                        {user ? 'Buka Dashboard' : 'Login CRM'}
                                    </Link>
                                </div>
                            </div>
                            <img src="/assets/landing/crm-team-avatars-3d.png" alt="Santovate CRM team" loading="lazy"/>
                        </div>
                    </div>
                </section>
            </main>

            <LandingFooter/>
        </div>
    );
}
