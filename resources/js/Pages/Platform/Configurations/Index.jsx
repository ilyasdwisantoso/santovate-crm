import { useForm } from '@inertiajs/react';
import PlatformLayout from '../../../Layouts/PlatformLayout';
const money=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));
function ConfigCard({config}){
 const form=useForm({name:config.name,description:config.description||'',monthly_addon_price:config.monthly_addon_price,annual_addon_price:config.annual_addon_price,is_active:config.is_active,sort_order:config.sort_order||0});
 const submit=e=>{e.preventDefault();form.put(`/platform/configurations/${config.id}`,{preserveScroll:true});};
 return <form className="platform-config-card" onSubmit={submit}><div className="platform-config-card-head"><div><span>{config.industry}</span><h2>{config.name}</h2><small>{config.clients} active clients · {money(config.mrr)} MRR</small></div><label className="platform-switch"><input type="checkbox" checked={form.data.is_active} onChange={e=>form.setData('is_active',e.target.checked)}/><i/></label></div>
  <div className="platform-form-grid"><label><span>Name</span><input value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/></label><label><span>Monthly add-on</span><input type="number" min="0" value={form.data.monthly_addon_price} onChange={e=>form.setData('monthly_addon_price',e.target.value)}/></label><label><span>Annual add-on</span><input type="number" min="0" value={form.data.annual_addon_price} onChange={e=>form.setData('annual_addon_price',e.target.value)}/></label><label><span>Sort order</span><input type="number" min="0" value={form.data.sort_order} onChange={e=>form.setData('sort_order',e.target.value)}/></label></div>
  {Object.values(form.errors).length>0&&<p className="platform-error">{Object.values(form.errors)[0]}</p>}
  <div className="platform-config-card-foot"><small>Perubahan harga hanya untuk subscription baru/perubahan berikutnya. Subscription aktif menyimpan snapshot amount.</small><button className="platform-button" disabled={form.processing}>{form.processing?'Saving…':'Save Configuration'}</button></div>
 </form>
}
export default function Configurations({configurations=[]}){return <PlatformLayout title="Business Configurations" subtitle="Kelola positioning dan harga add-on; pantau MRR setiap vertical."><section className="platform-config-grid">{configurations.map(c=><ConfigCard config={c} key={c.id}/>)}</section></PlatformLayout>}
