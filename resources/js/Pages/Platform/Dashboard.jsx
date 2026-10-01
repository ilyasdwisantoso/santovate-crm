import { Link } from '@inertiajs/react';
import PlatformLayout from '../../Layouts/PlatformLayout';

const money = (v)=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));
const dt = (v)=>v?new Date(v).toLocaleString('id-ID',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):'—';

export default function Dashboard({metrics={},configuration_breakdown=[],plan_mix=[],recent_payments=[],approval_inbox=[],recent_audit=[]}){
 const cards=[
  ['MRR',money(metrics.mrr),'Base + configuration + add-ons'],
  ['ARR',money(metrics.arr),'MRR × 12'],
  ['Active Clients',metrics.active_clients||0,'Real paying workspaces'],
  ['Cash MTD',money(metrics.cash_collected_mtd),'Subscription + add-on cash this month'],
 ];
 return <PlatformLayout title="Platform Overview" subtitle="Subscription health, configuration revenue, dan keputusan yang membutuhkan Owner.">
  <section className="platform-kpi-grid">{cards.map(([l,v,s])=><article key={l}><span>{l}</span><strong>{v}</strong><small>{s}</small></article>)}</section>
  <section className="platform-alert-strip">
   <div><span>Pending approvals</span><strong>{metrics.pending_approvals||0}</strong><Link href="/platform/approvals">Review</Link></div>
   <div><span>Pending payments</span><strong>{metrics.pending_payments||0}</strong><small>Subscription checkout</small></div>
   <div><span>Active subscriptions</span><strong>{metrics.active_subscriptions||0}</strong><small>Across real clients Â· {metrics.active_addons||0} active add-ons</small></div>
  </section>

  <div className="platform-grid-2">
   <section className="platform-panel"><div className="platform-panel-head"><div><span>REVENUE MIX</span><h2>By Business Configuration</h2></div><Link href="/platform/configurations">Manage</Link></div>
    <div className="platform-config-list">{configuration_breakdown.map(c=><div key={c.id}><div><strong>{c.name}</strong><small>{c.industry} · {c.clients} clients · Base {money(c.base_mrr)} · Config {money(c.configuration_mrr)} Â· Add-ons {money(c.addon_mrr)}</small></div><span><b>{money(c.mrr)}</b><small>Total MRR</small></span></div>)}</div>
   </section>
   <section className="platform-panel"><div className="platform-panel-head"><div><span>PLAN MIX</span><h2>Active subscription distribution</h2></div></div>
    <div className="platform-plan-mix">{plan_mix.map(p=><div key={p.name}><span>{p.name}</span><strong>{p.count}</strong><small>{money(p.mrr)} MRR</small></div>)}</div>
   </section>
  </div>

  <div className="platform-grid-2">
   <section className="platform-panel"><div className="platform-panel-head"><div><span>NEEDS YOUR DECISION</span><h2>Approval Inbox</h2></div><Link href="/platform/approvals">View all</Link></div>
    {approval_inbox.length?<div className="platform-approval-list">{approval_inbox.map(a=><Link href="/platform/approvals" key={a.id}><div><b>{a.type_label}</b><strong>{a.title}</strong><small>{a.organization} · {a.requester} · {dt(a.requested_at)}</small></div>{a.amount!==null&&<span>{money(a.amount)}</span>}</Link>)}</div>:<div className="platform-empty">Tidak ada keputusan yang menunggu.</div>}
   </section>
   <section className="platform-panel"><div className="platform-panel-head"><div><span>SUBSCRIPTION CASH</span><h2>Recent payments</h2></div></div>
    <div className="platform-payment-list">{recent_payments.map(p=><div key={p.id}><div><strong>{p.organization||'Workspace'}</strong><small>{p.label||'Subscription'} · {p.provider}</small></div><span><b>{money(p.amount)}</b><small className={`platform-status ${p.status}`}>{p.status}</small></span></div>)}</div>
   </section>
  </div>

  <section className="platform-panel"><div className="platform-panel-head"><div><span>AUDIT</span><h2>Recent owner decisions</h2></div></div>
   {recent_audit.length?<div className="platform-audit-list">{recent_audit.map(log=><div key={log.id}><strong>{log.action.replaceAll('.',' · ')}</strong><span>{log.actor||'System'}</span><small>{dt(log.created_at)}</small></div>)}</div>:<div className="platform-empty">Audit platform akan muncul setelah keputusan pertama.</div>}
  </section>
 </PlatformLayout>
}
