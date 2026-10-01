import { Link } from '@inertiajs/react';
import { useCallback } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PaymentInstruction from '../../Components/PaymentInstruction';
import usePaymentStream from '../../Hooks/usePaymentStream';

const money=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));

export default function AddonPaymentResult({payment,order,presentation,gateway={}}){
 const ref=payment?.reference_id;
 const done=useCallback(()=>window.setTimeout(()=>{window.location.href='/subscription/addons';},1200),[]);
 const {payment:live,connection}=usePaymentStream({reference:ref,streamUrl:ref?`/subscription/addons/payment-stream?reference=${encodeURIComponent(ref)}`:null,statusUrl:ref?`/subscription/addons/status?reference=${encodeURIComponent(ref)}`:null,initialStatus:payment?.status||'unknown',onComplete:done});
 const active=live.active||order?.status==='activated';
 return <AppLayout title="Status Add-on" subtitle="Verifikasi pembayaran dan aktivasi entitlement."><div className="addon-result panel">
  <div className={`addon-result-icon ${active?'done':''}`}>{active?'✓':'↻'}</div>
  <span className="eyebrow">iPaymu {gateway.mode} · {connection}</span>
  <h2>{active?'Add-on sudah aktif':'Menunggu konfirmasi pembayaran'}</h2>
  <p>{active?'Entitlement workspace sudah diperbarui otomatis.':'Selesaikan instruksi pembayaran dan jangan membuat pembayaran kedua untuk reference yang sama.'}</p>
  {!active&&<PaymentInstruction presentation={presentation}/>}
  <dl><div><dt>Reference</dt><dd>{ref||'—'}</dd></div><div><dt>Add-on</dt><dd>{order?.addon?.name||'—'}</dd></div><div><dt>Total</dt><dd>{money(payment?.amount)}</dd></div><div><dt>Status</dt><dd>{String(live.status||payment?.status||'unknown').toUpperCase()}</dd></div></dl>
  {payment?.failure_reason&&<p className="platform-error">{payment.failure_reason}</p>}
  <Link href="/subscription/addons" className="btn btn-primary">Kembali ke Add-ons</Link>
 </div></AppLayout>
}
