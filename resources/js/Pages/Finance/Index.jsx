import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { MetricCard, Badge } from '../../Components/Ui';
import { compactMoney, date, money } from '../../Utils/format';

const statusLabel = {
    draft:'Draft', issued:'Issued', partially_paid:'Partially Paid',
    paid:'Paid', overdue:'Overdue', void:'Void',
};
const statusTone = { draft:'neutral', issued:'info', partially_paid:'warning', paid:'success', overdue:'danger', void:'neutral' };

export default function FinanceIndex({ invoices, summary, filters, canManage }) {
    const change = (key, value) => router.get('/finance', { ...filters, [key]: value || undefined }, { preserveState:true, replace:true });

    return <AppLayout
        title="Finance"
        subtitle="Invoice, pembayaran client, outstanding, refund, dan komisi dalam satu sumber data."
        action={<div className="action-group">
            <Link href="/finance/commissions" className="btn btn-soft">Commission Ledger</Link>
            {canManage && <Link href="/finance/invoices/create" className="btn btn-primary"><Icon name="plus" size={17}/>Buat Invoice</Link>}
        </div>}
    >
        <Head title="Finance"/>

        <section className="metric-grid finance-metric-grid">
            <MetricCard label="Actual Deal" value={`Rp${compactMoney(summary.actual_deal)}`} helper="deal won yang dapat diakses" icon="check" accent="green"/>
            <MetricCard label="Invoiced" value={`Rp${compactMoney(summary.invoiced)}`} helper="invoice issued, partial, paid" icon="briefcase" accent="blue"/>
            <MetricCard label="Paid by Client" value={`Rp${compactMoney(summary.paid_by_client)}`} helper="net setelah refund" icon="check" accent="green"/>
            <MetricCard label="Outstanding" value={`Rp${compactMoney(summary.outstanding)}`} helper={`${summary.overdue_count || 0} invoice overdue`} icon="alert" accent="red"/>
            <MetricCard label="Potential Commission" value={`Rp${compactMoney(summary.potential_commission)}`} helper="25% commissionable value" icon="target" accent="violet"/>
            <MetricCard label="Earned Commission" value={`Rp${compactMoney(summary.earned_commission)}`} helper="berdasarkan pembayaran terverifikasi" icon="target" accent="amber"/>
            <MetricCard label="Commission Paid" value={`Rp${compactMoney(summary.commission_paid)}`} helper="payout yang telah dicatat" icon="check" accent="green"/>
        </section>

        <section className="panel commercial-toolbar finance-toolbar">
            <input value={filters.q || ''} onChange={e=>change('q', e.target.value)} placeholder="Cari invoice / deal / client..."/>
            <select value={filters.status || ''} onChange={e=>change('status', e.target.value)}>
                <option value="">Semua Status</option>
                {Object.entries(statusLabel).map(([value,label])=><option value={value} key={value}>{label}</option>)}
            </select>
        </section>

        <section className="panel no-pad">
            <div className="clean-table-wrap">
                <table className="clean-table finance-table">
                    <thead><tr><th>Invoice</th><th>Client / Deal</th><th>AE</th><th>Issued / Due</th><th>Total</th><th>Net Paid</th><th>Outstanding</th><th>Status</th><th/></tr></thead>
                    <tbody>
                        {invoices.data.map(invoice => <tr key={invoice.id}>
                            <td><strong>{invoice.invoice_number}</strong>{invoice.schedule && <small className="cell-sub">{invoice.schedule}</small>}</td>
                            <td><strong>{invoice.deal.client || '—'}</strong><small className="cell-sub">{invoice.deal.deal_number}</small></td>
                            <td>{invoice.deal.owner || '—'}</td>
                            <td><span>{date(invoice.issue_date)}</span><small className="cell-sub">Due {date(invoice.due_date)}</small></td>
                            <td>{money(invoice.total_amount)}</td>
                            <td>{money(Math.max(0, invoice.paid_amount - invoice.refunded_amount))}</td>
                            <td><strong className={invoice.outstanding_amount > 0 ? 'text-danger' : 'text-success'}>{money(invoice.outstanding_amount)}</strong></td>
                            <td><Badge tone={statusTone[invoice.status] || 'neutral'}>{statusLabel[invoice.status] || invoice.status}</Badge></td>
                            <td><Link className="icon-button" href={`/finance/invoices/${invoice.id}`}><Icon name="chevron" size={17}/></Link></td>
                        </tr>)}
                        {!invoices.data.length && <tr><td colSpan="9"><div className="empty-state"><h3>Belum ada invoice</h3><p>Invoice dapat dibuat dari deal won oleh Finance atau Administrator.</p></div></td></tr>}
                    </tbody>
                </table>
            </div>
            {invoices.links?.length > 3 && <div className="table-footer">
                <span>Page {invoices.current_page} of {invoices.last_page}</span>
                <div className="pagination">{invoices.links.map((link,index)=>link.url?<Link key={index} href={link.url} className={link.active?'active':''} dangerouslySetInnerHTML={{__html:link.label}}/>:<span key={index} dangerouslySetInnerHTML={{__html:link.label}}/>)}</div>
            </div>}
        </section>
    </AppLayout>;
}
