import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Badge, EmptyState } from '../../Components/Ui';
import { money } from '../../Utils/format';

const tone=(status)=>status==='accepted'?'success':status==='pending_approval'?'warning':status==='rejected'?'danger':status==='sent'?'info':'neutral';
export default function Index({quotations,filters,statuses}){
    const change=(k,v)=>router.get('/quotations',{...filters,[k]:v},{preserveState:true,replace:true});
    return <AppLayout title="Quotations" subtitle="Penawaran client dengan item, harga, approval, revisi, dan acceptance." action={<Link href="/quotations/create" className="btn btn-primary"><Icon name="plus" size={17}/>Quotation</Link>}>
        <Head title="Quotations"/>
        <section className="sv-context-banner"><span className="sv-context-icon"><Icon name="briefcase" size={19}/></span><div><strong>Item quotation = apa yang benar-benar dijual ke client.</strong><p>Gunakan Solution Pricebook untuk extension/integration. Internal cost tidak pernah ditampilkan di dokumen client.</p></div><Link href="/sales-guide#solution-pricebook">Lihat Pricebook <Icon name="chevron" size={14}/></Link></section>
        <section className="panel commercial-toolbar sv-toolbar-modern"><div className="search-box"><Icon name="search" size={17}/><input value={filters.q||''} onChange={e=>change('q',e.target.value)} placeholder="Cari quotation / client..."/></div><select value={filters.status||''} onChange={e=>change('status',e.target.value)}><option value="">Semua status</option>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></section>
        <section className="panel no-pad sv-data-panel">{quotations.data.length?<>
            <div className="clean-table-wrap desktop-only"><table className="clean-table sv-data-table"><thead><tr><th>Quotation</th><th>Client</th><th>Revision</th><th>Status</th><th>Total</th><th>Valid Until</th><th/></tr></thead><tbody>{quotations.data.map(q=><tr key={q.id}><td><Link href={`/quotations/${q.id}`} className="sv-primary-cell"><strong>{q.quotation_number}</strong><small>{q.opportunity?.name||'Direct quotation'}</small></Link></td><td><strong>{q.prospect?.company_name||'—'}</strong></td><td><span className="sv-revision-pill">Rev.{q.revision_number}</span></td><td><Badge tone={tone(q.status)}>{q.status_label}</Badge></td><td><strong>{money(q.grand_total)}</strong></td><td>{q.valid_until||'—'}</td><td><Link className="icon-button" href={`/quotations/${q.id}`}><Icon name="chevron" size={17}/></Link></td></tr>)}</tbody></table></div>
            <div className="sv-mobile-data-list mobile-only">{quotations.data.map(q=><Link href={`/quotations/${q.id}`} className="sv-mobile-data-card" key={q.id}><div className="sv-mobile-card-head"><div><small>{q.quotation_number} · Rev.{q.revision_number}</small><strong>{q.prospect?.company_name||'Client'}</strong></div><Badge tone={tone(q.status)}>{q.status_label}</Badge></div><div className="sv-mobile-card-metrics"><span><small>Total</small><strong>{money(q.grand_total)}</strong></span><span><small>Valid until</small><strong>{q.valid_until||'—'}</strong></span></div>{q.opportunity?.name&&<div className="sv-mobile-card-row"><span>Opportunity</span><strong>{q.opportunity.name}</strong></div>}</Link>)}</div>
        </>:<EmptyState icon="briefcase" title="Belum ada quotation" description="Buat dari Opportunity agar solution yang sudah dipilih dapat dibawa otomatis ke quotation." action={<Link href="/opportunities" className="btn btn-primary">Buka Opportunities</Link>}/>}</section>
    </AppLayout>;
}
