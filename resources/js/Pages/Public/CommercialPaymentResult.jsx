import PublicShell from './PublicShell';
import { money, dateTime } from '../../Utils/format';

export default function CommercialPaymentResult({ payment }) {
    return <PublicShell title="Payment Result">
        <section className="sv-result-card">
            <span className="eyebrow">Commercial Payment</span>
            <h1>{payment ? payment.invoice_number : 'Payment tidak ditemukan'}</h1>
            {payment ? <>
                <div className="sv-status">{payment.status}</div>
                <p>Reference: <strong>{payment.reference_id}</strong></p>
                <p>Amount: <strong>{money(payment.amount)}</strong></p>
                {payment.organization&&<p>Merchant: <strong>{payment.organization}</strong></p>}
                {payment.paid_at&&<p>Paid at: <strong>{dateTime(payment.paid_at)}</strong></p>}
                <p>Status akan diperbarui otomatis setelah callback payment gateway tervalidasi.</p>
            </> : <p>Reference pembayaran tidak tersedia atau tidak valid.</p>}
        </section>
    </PublicShell>;
}
