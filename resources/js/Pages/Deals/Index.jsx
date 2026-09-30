import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { EmptyState } from '../../Components/Ui';
import { money } from '../../Utils/format';

export default function Index({deals,filters}){
    const change=v=>router.get('/deals',{q:v},{preserveState:true,replace:true});
    return <AppLayout title="Deals" subtitle="Nilai yang benar-benar disepakati setelah quotation Accepted." action={<Link href="/quotations?status=accepted" className="btn btn-soft">Accepted Quotations</Link>}>
        <Head title="Deals"/>
        <section className="sv-context-banner"><span className="sv-context-icon"><Icon name="check" size={19}/></span><div><strong>Deal bukan quotation.</strong><p>Deal adalah nilai komersial final yang disepakati dan menjadi dasar payment schedule, invoice, collection, serta commissionable value.</p></div><Link href="/sales-guide">Panduan <Icon name="chevron" size={14}/></Link></section>
        <section className="panel commercial-toolbar sv-toolbar-modern"><div className="search-box"><Icon name="search" size={17}/><input value={filters.q||''} onChange={e=>change(e.target.value)} placeholder="Cari deal / client..."/></div></section>
        <section className="panel no-pad sv-data-panel">{deals.data.length?<>
            <div className="clean-table-wrap desktop-only"><table className="clean-table sv-data-table"><thead><tr><th>Deal</th><th>Client</th><th>Owner</th><th>Closing</th><th>Actual Deal</th><th>Commissionable</th><th/></tr></thead><tbody>{deals.data.map(d=><tr key={d.id}><td><Link href={`/deals/${d.id}`} className="sv-primary-cell"><strong>{d.deal_number}</strong><small>{d.quotation?.quotation_number||'—'}</small></Link></td><td><strong>{d.prospect?.company_name||'—'}</strong></td><td>{d.owner?.name||'—'}</td><td>{d.closing_date||'—'}</td><td><strong>{money(d.actual_deal_value)}</strong></td><td>{money(d.commissionable_value)}</td><td><Link className="icon-button" href={`/deals/${d.id}`}><Icon name="chevron" size={17}/></Link></td></tr>)}</tbody></table></div>
            <div className="sv-mobile-data-list mobile-only">{deals.data.map(d=><Link href={`/deals/${d.id}`} className="sv-mobile-data-card" key={d.id}><div className="sv-mobile-card-head"><div><small>{d.deal_number}</small><strong>{d.prospect?.company_name||'Client'}</strong></div><Icon name="chevron" size={17}/></div><div className="sv-mobile-card-metrics"><span><small>Actual Deal</small><strong>{money(d.actual_deal_value)}</strong></span><span><small>Commissionable</small><strong>{money(d.commissionable_value)}</strong></span></div><div className="sv-mobile-card-row"><span>Owner</span><strong>{d.owner?.name||'—'}</strong></div><div className="sv-mobile-card-row"><span>Closing</span><strong>{d.closing_date||'—'}</strong></div></Link>)}</div>
        </>:<EmptyState icon="check" title="Belum ada Deal" description="Deal dibuat dari quotation yang sudah Accepted." action={<Link href="/quotations?status=accepted" className="btn btn-primary">Accepted Quotations</Link>}/>}</section>
    </AppLayout>;
}
