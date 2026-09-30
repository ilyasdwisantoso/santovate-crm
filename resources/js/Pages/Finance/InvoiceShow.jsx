import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Badge } from '../../Components/Ui';
import { date, dateTime, money } from '../../Utils/format';

const labels={draft:'Draft',issued:'Issued',partially_paid:'Partially Paid',paid:'Paid',overdue:'Overdue',void:'Void',pending_verification:'Pending Verification',waiting_payment:'Waiting Payment',rejected:'Rejected',failed:'Failed',refunded:'Refunded'};
const tones={draft:'neutral',issued:'info',partially_paid:'warning',paid:'success',overdue:'danger',void:'neutral',pending_verification:'warning',waiting_payment:'info',rejected:'danger',failed:'danger',refunded:'violet'};

export default function InvoiceShow({ invoice:i, canManage, ipaymuConfigured }) {
    const manual=useForm({amount:i.outstanding_amount||'',transfer_date:new Date().toISOString().slice(0,10),bank_name:'',sender_name:'',proof:null});
    const gateway=useForm({payment_method:'va',payment_channel:'bca'});
    const refund=useForm({payment_transaction_id:'',amount:'',reason:'',reference_number:'',refunded_at:new Date().toISOString().slice(0,10)});

    const submitManual=e=>{e.preventDefault();manual.post(`/finance/invoices/${i.id}/manual-payments`,{forceFormData:true,onSuccess:()=>manual.reset('bank_name','sender_name','proof')})};
    const submitGateway=e=>{e.preventDefault();gateway.post(`/finance/invoices/${i.id}/gateway-payments`)};
    const submitRefund=e=>{e.preventDefault();if(!refund.data.payment_transaction_id)return;refund.post(`/finance/payments/${refund.data.payment_transaction_id}/refund`,{onSuccess:()=>refund.reset('payment_transaction_id','amount','reason','reference_number')})};
    const reject=(payment)=>{const reason=window.prompt('Alasan penolakan bukti pembayaran:');if(reason)router.post(`/finance/payments/${payment.id}/reject`,{reason});};
    const voidInvoice=()=>{const reason=window.prompt('Alasan void invoice:');if(reason)router.post(`/finance/invoices/${i.id}/void`,{reason});};

    const paidTransactions=i.transactions.filter(p=>['paid','refunded'].includes(p.status));

    return <AppLayout
        title={i.invoice_number}
        subtitle={`${i.deal.client || ''} · ${i.deal.deal_number}`}
        action={<div className="action-group"><Link href="/finance" className="btn btn-secondary"><Icon name="arrowLeft" size={17}/>Finance</Link><Link href={`/finance/invoices/${i.id}/print`} className="btn btn-soft" target="_blank">Print</Link></div>}
    >
        <Head title={i.invoice_number}/>

        <section className="metric-grid finance-invoice-metrics">
            <div className="panel"><span>Total Invoice</span><strong>{money(i.total_amount)}</strong></div>
            <div className="panel"><span>Net Paid</span><strong>{money(i.net_paid_amount)}</strong></div>
            <div className="panel"><span>Refunded</span><strong>{money(i.refunded_amount)}</strong></div>
            <div className="panel"><span>Outstanding</span><strong className={i.outstanding_amount>0?'text-danger':'text-success'}>{money(i.outstanding_amount)}</strong></div>
        </section>

        <div className="finance-detail-layout">
            <div className="finance-detail-main">
                <section className="panel">
                    <div className="panel-head"><div><span className="eyebrow">Invoice</span><h3>Billing detail</h3></div><Badge tone={tones[i.status]||'neutral'}>{labels[i.status]||i.status}</Badge></div>
                    <div className="commercial-detail-grid">
                        <div><span>Client</span><p>{i.deal.client||'—'}</p></div>
                        <div><span>Account Executive</span><p>{i.deal.owner||'—'}</p></div>
                        <div><span>Issue Date</span><p>{date(i.issue_date)}</p></div>
                        <div><span>Due Date</span><p>{date(i.due_date)}</p></div>
                        <div><span>Subtotal</span><p>{money(i.subtotal)}</p></div>
                        <div><span>Tax</span><p>{money(i.tax_amount)}</p></div>
                        {i.schedule&&<div><span>Payment Schedule</span><p>{i.schedule.label} · {money(i.schedule.amount)}</p></div>}
                        <div><span>Issuer</span><p>{i.issuer||'—'}</p></div>
                    </div>
                    {i.terms&&<div className="notes-box"><span>Terms</span><p>{i.terms}</p></div>}
                    {i.notes&&<div className="notes-box"><span>Notes</span><p>{i.notes}</p></div>}
                    {i.void_reason&&<div className="alert alert-danger">Void reason: {i.void_reason}</div>}
                    {canManage&&i.status==='draft'&&<button className="btn btn-primary" onClick={()=>router.post(`/finance/invoices/${i.id}/issue`)}>Issue Invoice</button>}
                    {canManage&&['draft','issued','overdue'].includes(i.status)&&<button className="btn btn-danger-soft" onClick={voidInvoice}>Void Invoice</button>}
                </section>

                <section className="panel">
                    <div className="panel-head"><div><span className="eyebrow">Payment Transactions</span><h3>Audit trail pembayaran</h3></div></div>
                    <div className="clean-table-wrap">
                        <table className="clean-table">
                            <thead><tr><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th><th>Evidence / Link</th><th>Verified</th><th>Action</th></tr></thead>
                            <tbody>
                                {i.transactions.map(p=><tr key={p.id}>
                                    <td><strong>{p.reference_id}</strong><small className="cell-sub">{dateTime(p.created_at)}</small></td>
                                    <td>{p.provider==='manual'?<><strong>Manual Transfer</strong><small className="cell-sub">{p.bank_name} · {p.sender_name}</small></>:<><strong>iPaymu</strong><small className="cell-sub">{p.payment_method} · {p.payment_channel}</small></>}</td>
                                    <td>{money(p.amount)}{p.refunded_amount>0&&<small className="cell-sub text-danger">Refund {money(p.refunded_amount)}</small>}</td>
                                    <td><Badge tone={tones[p.status]||'neutral'}>{labels[p.status]||p.status}</Badge>{p.rejection_reason&&<small className="cell-sub text-danger">{p.rejection_reason}</small>}{p.failure_reason&&<small className="cell-sub text-danger">{p.failure_reason}</small>}</td>
                                    <td>{p.proof_url?<a href={p.proof_url} target="_blank" rel="noreferrer" className="text-link">Bukti transfer</a>:p.checkout_url?<a href={p.checkout_url} target="_blank" rel="noreferrer" className="text-link">Payment Link</a>:'—'}</td>
                                    <td>{p.verified_at?<><strong>{p.verified_by||'System'}</strong><small className="cell-sub">{dateTime(p.verified_at)}</small></>:'—'}</td>
                                    <td>{canManage&&p.status==='pending_verification'?<div className="action-group"><button className="btn btn-sm btn-primary" onClick={()=>router.post(`/finance/payments/${p.id}/verify`)}>Verify</button><button className="btn btn-sm btn-danger-soft" onClick={()=>reject(p)}>Reject</button></div>:'—'}</td>
                                </tr>)}
                                {!i.transactions.length&&<tr><td colSpan="7"><div className="mini-empty">Belum ada payment transaction.</div></td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                {i.status!=='draft'&&i.status!=='void'&&i.outstanding_amount>0&&<section className="finance-payment-grid">
                    <form className="panel" onSubmit={submitManual}>
                        <div className="panel-head"><div><span className="eyebrow">Manual Transfer</span><h3>Upload proof of payment</h3></div></div>
                        <p className="finance-helper">Sales boleh upload bukti atas nama client. Hanya Finance/Admin yang dapat memverifikasi menjadi Paid.</p>
                        <div className="form-grid">
                            <label className="field"><span>Amount *</span><input type="number" min="1" max={i.outstanding_amount} value={manual.data.amount} onChange={e=>manual.setData('amount',e.target.value)}/></label>
                            <label className="field"><span>Transfer Date *</span><input type="date" value={manual.data.transfer_date} onChange={e=>manual.setData('transfer_date',e.target.value)}/></label>
                            <label className="field"><span>Bank *</span><input value={manual.data.bank_name} onChange={e=>manual.setData('bank_name',e.target.value)} placeholder="BCA / Mandiri / ..."/></label>
                            <label className="field"><span>Sender Name *</span><input value={manual.data.sender_name} onChange={e=>manual.setData('sender_name',e.target.value)} /></label>
                            <label className="field span-2"><span>Proof *</span><input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={e=>manual.setData('proof',e.target.files[0])}/>{manual.errors.proof&&<small className="field-error">{manual.errors.proof}</small>}</label>
                        </div>
                        <button className="btn btn-primary" disabled={manual.processing}>Submit for Verification</button>
                    </form>

                    <form className="panel" onSubmit={submitGateway}>
                        <div className="panel-head"><div><span className="eyebrow">Payment Gateway</span><h3>iPaymu payment link</h3></div></div>
                        <p className="finance-helper">{ipaymuConfigured?'Gateway configured. Callback yang tervalidasi akan otomatis mengubah payment menjadi Paid.':'Kredensial iPaymu belum dikonfigurasi di environment.'}</p>
                        <div className="form-grid">
                            <label className="field"><span>Payment Method</span><input value={gateway.data.payment_method} onChange={e=>gateway.setData('payment_method',e.target.value)}/></label>
                            <label className="field"><span>Payment Channel</span><input value={gateway.data.payment_channel} onChange={e=>gateway.setData('payment_channel',e.target.value)}/></label>
                        </div>
                        <button className="btn btn-primary" disabled={!ipaymuConfigured||gateway.processing}>Generate Payment Link · {money(i.outstanding_amount)}</button>
                    </form>
                </section>}

                {canManage&&paidTransactions.length>0&&<form className="panel" onSubmit={submitRefund}>
                    <div className="panel-head"><div><span className="eyebrow">Refund / Adjustment</span><h3>Catat refund tanpa menghapus transaksi asli</h3></div></div>
                    <div className="form-grid">
                        <label className="field span-2"><span>Payment *</span><select value={refund.data.payment_transaction_id} onChange={e=>refund.setData('payment_transaction_id',e.target.value)}><option value="">Pilih payment</option>{paidTransactions.map(p=><option value={p.id} key={p.id}>{p.reference_id} · {money(p.amount)} · refunded {money(p.refunded_amount)}</option>)}</select></label>
                        <label className="field"><span>Refund Amount *</span><input type="number" min="1" value={refund.data.amount} onChange={e=>refund.setData('amount',e.target.value)}/></label>
                        <label className="field"><span>Refund Date *</span><input type="date" value={refund.data.refunded_at} onChange={e=>refund.setData('refunded_at',e.target.value)}/></label>
                        <label className="field"><span>Reference</span><input value={refund.data.reference_number} onChange={e=>refund.setData('reference_number',e.target.value)}/></label>
                        <label className="field"><span>Reason *</span><input value={refund.data.reason} onChange={e=>refund.setData('reason',e.target.value)}/></label>
                    </div>
                    <button className="btn btn-danger-soft" disabled={refund.processing}>Record Refund</button>
                </form>}
            </div>

            <aside className="finance-detail-side">
                <section className="panel sticky-panel">
                    <span className="eyebrow">Accounts Receivable</span>
                    <h3 className="side-title">{i.deal.client}</h3>
                    <div className="finance-ar-stack">
                        <div><span>Invoice</span><strong>{money(i.total_amount)}</strong></div>
                        <div><span>Gross Paid</span><strong>{money(i.paid_amount)}</strong></div>
                        <div><span>Refunded</span><strong>{money(i.refunded_amount)}</strong></div>
                        <div className="grand"><span>Outstanding</span><strong>{money(i.outstanding_amount)}</strong></div>
                    </div>
                    <Link href={`/deals/${i.deal.id}`} className="btn btn-soft btn-block">Open Deal</Link>
                    <Link href="/finance/commissions" className="btn btn-secondary btn-block">Commission Ledger</Link>
                </section>
            </aside>
        </div>
    </AppLayout>;
}
