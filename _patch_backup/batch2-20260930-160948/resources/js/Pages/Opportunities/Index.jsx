import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { money, dateTime } from '../../Utils/format';

export default function Index({ opportunities, filters, stages, statuses }) {
    const change=(key,value)=>router.get('/opportunities',{...filters,[key]:value},{preserveState:true,replace:true});
    return <AppLayout title="Opportunities" subtitle="Discovery, kebutuhan, probability, dan peluang komersial per client." action={<Link href="/opportunities/create" className="btn btn-primary"><Icon name="plus" size={17}/>Opportunity</Link>}>
        <Head title="Opportunities"/>
        <section className="panel commercial-toolbar">
            <input value={filters.q||''} onChange={e=>change('q',e.target.value)} placeholder="Cari opportunity / perusahaan..."/>
            <select value={filters.stage||''} onChange={e=>change('stage',e.target.value)}><option value="">Semua stage</option>{Object.entries(stages).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select>
            <select value={filters.status||''} onChange={e=>change('status',e.target.value)}><option value="">Semua status</option>{Object.entries(statuses).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select>
        </section>
        <section className="panel no-pad">
            <div className="clean-table-wrap"><table className="clean-table"><thead><tr><th>Opportunity</th><th>Stage</th><th>Probability</th><th>Expected Value</th><th>Next Action</th><th>Owner</th><th/></tr></thead><tbody>
                {opportunities.data.map(o=><tr key={o.id}><td><strong>{o.name}</strong><small className="cell-sub">{o.prospect?.company_name||'—'}</small></td><td><span className="badge badge-info">{o.stage_label}</span></td><td>{o.probability}%</td><td>{money(o.expected_value)}</td><td><strong>{o.next_action||'—'}</strong><small className="cell-sub">{o.next_follow_up_at?dateTime(o.next_follow_up_at):'Belum dijadwalkan'}</small></td><td>{o.owner?.name||'Belum diassign'}</td><td><Link className="icon-button" href={`/opportunities/${o.id}`}><Icon name="chevron" size={17}/></Link></td></tr>)}
                {!opportunities.data.length&&<tr><td colSpan="7"><div className="empty-state"><h3>Belum ada opportunity</h3><p>Buat opportunity setelah prospek lolos discovery awal.</p></div></td></tr>}
            </tbody></table></div>
        </section>
    </AppLayout>;
}
