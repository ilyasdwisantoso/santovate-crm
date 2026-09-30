import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, MetricCard } from '../../Components/Ui';
import { compactMoney, money } from '../../Utils/format';

const labels={estimated:'Estimated',pending_payment:'Pending Payment',partially_earned:'Partially Earned',earned:'Earned',approved:'Approved',paid:'Paid'};
const tones={estimated:'neutral',pending_payment:'info',partially_earned:'warning',earned:'success',approved:'violet',paid:'success'};

export default function Commissions({ commissions, summary, canManage }) {
    const approve=(c)=>router.post(`/finance/commissions/${c.id}/approve`);
    const pay=(c)=>{const reference=window.prompt('Reference payout (opsional):')||'';router.post(`/finance/commissions/${c.id}/pay`,{reference_number:reference,paid_at:new Date().toISOString().slice(0,10)});};

    return <AppLayout title="Commission Ledger" subtitle="Komisi 25% dihitung dari commissionable value dan pembayaran client yang sudah terverifikasi." action={<Link href="/finance" className="btn btn-secondary">Finance</Link>}>
        <Head title="Commission Ledger"/>
        <section className="metric-grid finance-commission-metrics">
            <MetricCard label="Potential" value={`Rp${compactMoney(summary.potential_commission)}`} helper="maximum commission" icon="target" accent="violet"/>
            <MetricCard label="Earned" value={`Rp${compactMoney(summary.earned_commission)}`} helper="verified client payment" icon="check" accent="green"/>
            <MetricCard label="Approved" value={`Rp${compactMoney(summary.approved_commission)}`} helper="approved for payout" icon="briefcase" accent="blue"/>
            <MetricCard label="Paid" value={`Rp${compactMoney(summary.commission_paid)}`} helper="commission already paid" icon="check" accent="green"/>
        </section>
        <section className="panel no-pad">
            <div className="clean-table-wrap">
                <table className="clean-table">
                    <thead><tr><th>Deal / Client</th><th>Sales</th><th>Commissionable</th><th>Potential</th><th>Earned</th><th>Approved</th><th>Paid</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>{commissions.data.map(c=><tr key={c.id}>
                        <td><strong>{c.deal.deal_number}</strong><small className="cell-sub">{c.deal.client}</small>{c.adjustment_due>0&&<small className="cell-sub text-danger">Overpaid adjustment: {money(c.adjustment_due)}</small>}</td>
                        <td>{c.sales?.name || '—'}</td>
                        <td>{money(c.commissionable_value)}</td>
                        <td>{money(c.potential_amount)}</td>
                        <td>{money(c.earned_amount)}</td>
                        <td>{money(c.approved_amount)}</td>
                        <td>{money(c.paid_amount)}</td>
                        <td><Badge tone={tones[c.status]||'neutral'}>{labels[c.status]||c.status}</Badge></td>
                        <td>{canManage?<div className="action-group">{c.earned_amount>c.approved_amount+0.01&&<button className="btn btn-sm btn-soft" onClick={()=>approve(c)}>Approve Earned</button>}{c.payable_amount>0.01&&<button className="btn btn-sm btn-primary" onClick={()=>pay(c)}>Mark Paid</button>}</div>:'—'}</td>
                    </tr>)}{!commissions.data.length&&<tr><td colSpan="9"><div className="mini-empty">Belum ada commission ledger.</div></td></tr>}</tbody>
                </table>
            </div>
            {commissions.links?.length > 3 && <div className="table-footer">
                <span>Page {commissions.current_page} of {commissions.last_page}</span>
                <div className="pagination">{commissions.links.map((link,index)=>link.url?<Link key={index} href={link.url} className={link.active?'active':''} dangerouslySetInnerHTML={{__html:link.label}}/>:<span key={index} dangerouslySetInnerHTML={{__html:link.label}}/>)}</div>
            </div>}
        </section>
    </AppLayout>;
}
