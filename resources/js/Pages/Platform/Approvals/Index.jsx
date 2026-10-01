import { router } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '../../../Layouts/PlatformLayout';
const money=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));
const dt=v=>v?new Date(v).toLocaleString('id-ID',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
function ApprovalCard({a}){
 const [notes,setNotes]=useState(''); const [busy,setBusy]=useState(false);
 const decide=(decision)=>{if(decision==='rejected'&&!notes.trim()&&!confirm('Reject tanpa catatan?'))return;setBusy(true);router.post(`/platform/approvals/${a.id}/decision`,{decision,notes},{preserveScroll:true,onFinish:()=>setBusy(false)});};
 const quote=a.subject_type?.endsWith('Quotation')&&a.subject_id?`/quotations/${a.subject_id}`:null;
 return <article className={`platform-approval-card ${a.status}`}><div className="platform-approval-top"><div><span>{a.type_label}</span><h3>{a.title}</h3><small>{a.organization?.name} · {a.requester?.name} · {dt(a.requested_at)}</small></div>{a.amount!==null&&<strong>{money(a.amount)}</strong>}</div>
  {a.summary&&<p>{a.summary}</p>}{a.metadata?.reasons?.length>0&&<div className="platform-reasons">{a.metadata.reasons.map(r=><span key={r.code}>{r.label}</span>)}</div>}
  {a.metadata?.payment_terms&&<div className="platform-term-box"><small>Payment terms</small><strong>{a.metadata.payment_terms}</strong></div>}
  {quote&&<a className="platform-inline-link" href={quote}>Open quotation →</a>}
  {a.status==='pending'?<div className="platform-decision"><textarea value={notes} onChange={e=>setNotes(e.target.value)} placeholder="Catatan keputusan / kondisi approval…"/><div><button disabled={busy} onClick={()=>decide('rejected')} className="reject">Reject</button><button disabled={busy} onClick={()=>decide('approved_with_conditions')} className="condition">Approve + condition</button><button disabled={busy} onClick={()=>decide('approved')} className="approve">Approve</button></div></div>:<div className="platform-decision-result"><span>{a.status_label}</span><p>{a.decision_notes||'Tanpa catatan.'}</p><small>{a.decider?.name||'System'} · {dt(a.decided_at)}</small></div>}
 </article>
}
export default function Approvals({approvals}){return <PlatformLayout title="Approval Inbox" subtitle="Final price, discount, payment terms, custom scope, dan expense exception."><div className="platform-approval-stack">{approvals.data.map(a=><ApprovalCard a={a} key={a.id}/>)}{!approvals.data.length&&<div className="platform-panel platform-empty">Tidak ada approval pada filter ini.</div>}</div></PlatformLayout>}
