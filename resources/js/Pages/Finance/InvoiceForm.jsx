import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { money } from '../../Utils/format';

export default function InvoiceForm({ deals, defaults }) {
    const form = useForm(defaults);
    const deal = useMemo(()=>deals.find(d=>String(d.id)===String(form.data.deal_id)),[deals,form.data.deal_id]);
    const schedules = deal?.schedules || [];
    const schedule = schedules.find(s=>String(s.id)===String(form.data.payment_schedule_id));

    const setSchedule = (value) => {
        form.setData({
            ...form.data,
            payment_schedule_id:value,
            subtotal:value ? (schedules.find(s=>String(s.id)===String(value))?.remaining_invoice_amount || '') : form.data.subtotal,
            due_date:value ? (schedules.find(s=>String(s.id)===String(value))?.due_date || form.data.due_date) : form.data.due_date,
        });
    };

    const submit = e => { e.preventDefault(); form.post('/finance/invoices'); };
    const total = Number(form.data.subtotal || 0) + Number(form.data.tax_amount || 0);
    const remaining = schedule ? Number(schedule.remaining_invoice_amount || 0) : Number(deal?.remaining_invoice_amount || 0);
    const overAllocation = Boolean(deal) && total > remaining + 0.01;

    return <AppLayout title="Buat Invoice" subtitle="Terbitkan tagihan dari Deal Won atau payment schedule." action={<Link href="/finance" className="btn btn-secondary"><Icon name="arrowLeft" size={17}/>Finance</Link>}>
        <Head title="Buat Invoice"/>
        <form className="finance-form-layout" onSubmit={submit}>
            <section className="panel">
                <div className="panel-head"><div><span className="eyebrow">Invoice</span><h3>Billing information</h3></div></div>
                <div className="form-grid">
                    <label className="field span-2"><span>Deal *</span><select value={form.data.deal_id} onChange={e=>form.setData({...form.data,deal_id:e.target.value,payment_schedule_id:''})}><option value="">Pilih Deal Won</option>{deals.map(d=><option value={d.id} key={d.id}>{d.deal_number} · {d.client} · {money(d.actual_deal_value)}</option>)}</select>{form.errors.deal_id&&<small className="field-error">{form.errors.deal_id}</small>}</label>
                    <label className="field span-2"><span>Payment Schedule</span><select value={form.data.payment_schedule_id || ''} disabled={!deal} onChange={e=>setSchedule(e.target.value)}><option value="">Tanpa milestone spesifik</option>{schedules.map(s=><option value={s.id} key={s.id}>{s.label} · {money(s.amount)} · {s.status}</option>)}</select>{schedule&&<small className="field-hint">Schedule: {schedule.label} · Sisa dapat ditagihkan {money(schedule.remaining_invoice_amount)}</small>}{!schedule&&deal&&<small className="field-hint">Sisa Deal yang dapat ditagihkan: {money(deal.remaining_invoice_amount)}</small>}</label>
                    <label className="field"><span>Issue Date *</span><input type="date" value={form.data.issue_date} onChange={e=>form.setData('issue_date',e.target.value)}/>{form.errors.issue_date&&<small className="field-error">{form.errors.issue_date}</small>}</label>
                    <label className="field"><span>Due Date</span><input type="date" value={form.data.due_date || ''} onChange={e=>form.setData('due_date',e.target.value)}/>{form.errors.due_date&&<small className="field-error">{form.errors.due_date}</small>}</label>
                    <label className="field"><span>Subtotal *</span><input type="number" min="1" step="1" value={form.data.subtotal} onChange={e=>form.setData('subtotal',e.target.value)}/>{form.errors.subtotal&&<small className="field-error">{form.errors.subtotal}</small>}</label>
                    <label className="field"><span>Tax</span><input type="number" min="0" step="1" value={form.data.tax_amount} onChange={e=>form.setData('tax_amount',e.target.value)}/></label>
                    <label className="field"><span>Status awal</span><select value={form.data.status} onChange={e=>form.setData('status',e.target.value)}><option value="draft">Draft</option><option value="issued">Issued</option></select></label>
                    <div className="finance-total-preview"><span>Total Invoice</span><strong>{money(total)}</strong>{deal&&<small className={overAllocation?'field-error':'field-hint'}>Batas tersisa: {money(remaining)}{overAllocation?' · total melebihi alokasi':''}</small>}</div>
                    <label className="field span-2"><span>Terms</span><textarea rows="4" value={form.data.terms || ''} onChange={e=>form.setData('terms',e.target.value)} placeholder="Payment terms, bank instruction, scope..."/></label>
                    <label className="field span-2"><span>Notes</span><textarea rows="4" value={form.data.notes || ''} onChange={e=>form.setData('notes',e.target.value)} placeholder="Catatan internal / customer..."/></label>
                </div>
                <div className="form-submit-row"><Link href="/finance" className="btn btn-secondary">Batal</Link><button className="btn btn-primary" disabled={form.processing || overAllocation}>Simpan Invoice</button></div>
            </section>
        </form>
    </AppLayout>;
}
