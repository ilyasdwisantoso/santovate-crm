import { useMemo, useState } from 'react';
import PlatformLayout from '../../../Layouts/PlatformLayout';

const money=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));
const cap=r=>r?.unlimited?'∞':`${r?.used||0} / ${r?.limit||0}`;
export default function Clients({clients=[]}){
 const [q,setQ]=useState('');
 const rows=useMemo(()=>clients.filter(c=>`${c.name} ${c.slug} ${c.configuration?.name||''} ${c.subscription?.plan?.name||''}`.toLowerCase().includes(q.toLowerCase())),[clients,q]);
 return <PlatformLayout title="Clients" subtitle="Registry tenant + effective entitlement. Revenue analytics tetap memisahkan demo dan Santovate Internal." action={<a className="platform-button" href="/admin/clients">Manual Activation</a>}>
  <section className="platform-toolbar"><input value={q} onChange={e=>setQ(e.target.value)} placeholder="Cari client, plan, configuration…"/><span>{rows.length} workspace</span></section>
  <section className="platform-panel no-pad"><div className="platform-table-wrap"><table className="platform-table"><thead><tr><th>Client</th><th>Configuration</th><th>Plan</th><th>Users</th><th>Prospects</th><th>Features</th><th>Subscription</th><th>Amount</th></tr></thead><tbody>{rows.map(c=><tr key={c.id}><td><strong>{c.name}</strong><small>{c.slug}{c.is_demo?' · DEMO':''}</small></td><td>{c.configuration?.name||'—'}</td><td><strong>{c.subscription?.plan?.name||'—'}</strong><small>{c.subscription?.billing_cycle||''}</small></td><td>{cap(c.entitlements?.users)}</td><td>{cap(c.entitlements?.prospects)}</td><td><strong>{c.entitlements?.enabled_features||0}</strong><small>{c.entitlements?.active_grants?`${c.entitlements.active_grants} grant`:''}</small></td><td><span className={`platform-status ${c.subscription?.status||c.status}`}>{c.subscription?.status||c.status}</span></td><td>{c.subscription?money(c.subscription.total_amount):'—'}</td></tr>)}</tbody></table></div>
   <div className="platform-mobile-cards">{rows.map(c=><article key={c.id}><div><strong>{c.name}</strong><small>{c.configuration?.name||'No configuration'}</small></div><span className={`platform-status ${c.subscription?.status||c.status}`}>{c.subscription?.status||c.status}</span><dl><div><dt>Plan</dt><dd>{c.subscription?.plan?.name||'—'}</dd></div><div><dt>Users</dt><dd>{cap(c.entitlements?.users)}</dd></div><div><dt>Prospects</dt><dd>{cap(c.entitlements?.prospects)}</dd></div><div><dt>Features</dt><dd>{c.entitlements?.enabled_features||0}</dd></div><div><dt>Amount</dt><dd>{c.subscription?money(c.subscription.total_amount):'—'}</dd></div></dl></article>)}</div>
  </section>
 </PlatformLayout>
}
