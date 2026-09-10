import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { Avatar, Progress } from '../../Components/Ui';
import { compactMoney, money } from '../../Utils/format';

const monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
function TargetMetric({label,actual,goal,progress,kind}) { return <div className="target-metric"><div><span>{label}</span><strong>{kind==='money'?`${compactMoney(actual)} / ${compactMoney(goal)}`:`${actual} / ${goal}`}</strong></div><Progress value={progress}/><small>{progress}% tercapai</small></div>; }

function TargetModal({ row, period, onClose }) {
    const p=row.performance;
    const form=useForm({year:period.year,month:period.month,target_contacted:p.goals.contacted,target_meetings:p.goals.meetings,target_proposals:p.goals.proposals,target_deals:p.goals.deals,target_revenue:p.goals.revenue});
    const submit=(e)=>{e.preventDefault();form.put(`/targets/${row.user.id}`,{preserveScroll:true,onSuccess:onClose});};
    return <div className="modal-backdrop" onClick={onClose}><form className="modal-card" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="modal-head"><div><span className="eyebrow">Set target</span><h3>{row.user.name}</h3><p>{monthNames[period.month-1]} {period.year}</p></div><button type="button" className="icon-button" onClick={onClose}><Icon name="close" size={20}/></button></div><div className="form-grid single-mobile">
        <label className="field"><span>Perusahaan dihubungi</span><input type="number" min="0" value={form.data.target_contacted} onChange={e=>form.setData('target_contacted',e.target.value)}/></label>
        <label className="field"><span>Meeting / discovery</span><input type="number" min="0" value={form.data.target_meetings} onChange={e=>form.setData('target_meetings',e.target.value)}/></label>
        <label className="field"><span>Proposal dikirim</span><input type="number" min="0" value={form.data.target_proposals} onChange={e=>form.setData('target_proposals',e.target.value)}/></label>
        <label className="field"><span>Deal closed</span><input type="number" min="0" value={form.data.target_deals} onChange={e=>form.setData('target_deals',e.target.value)}/></label>
        <label className="field span-2"><span>Target revenue</span><input type="number" min="0" step="100000" value={form.data.target_revenue} onChange={e=>form.setData('target_revenue',e.target.value)}/><small className="field-hint">{money(form.data.target_revenue)}</small></label>
    </div>{Object.keys(form.errors).length>0&&<div className="alert alert-error">{Object.values(form.errors)[0]}</div>}<div className="modal-actions"><button type="button" className="btn btn-secondary" onClick={onClose}>Batal</button><button className="btn btn-primary" disabled={form.processing}>Simpan Target</button></div></form></div>;
}

export default function Targets({ rows, period, canManage }) {
    const [editing,setEditing]=useState(null);
    const years=useMemo(()=>[period.year-1,period.year,period.year+1], [period.year]);
    const changePeriod=(key,value)=>router.get('/targets',{...period,[key]:value},{preserveState:true});
    return <AppLayout title="Target & Performance" subtitle="Ukur hasil Account Executive dari aktivitas yang bisa dikendalikan sampai revenue yang benar-benar dihasilkan.">
        <Head title="Target Account Executive"/>
        <section className="target-explainer panel"><div className="target-explainer-icon"><Icon name="target" size={25}/></div><div><h3>Kenapa bukan “target handle company”?</h3><p>Jumlah perusahaan aktif tetap dipantau sebagai <b>workload</b>, tetapi bukan target utama. KPI utama harus mendorong progres nyata: dihubungi → meeting → proposal → deal → revenue.</p></div><div className="weight-chips"><span>Kontak <b>10%</b></span><span>Meeting <b>20%</b></span><span>Proposal <b>20%</b></span><span>Deal <b>20%</b></span><span>Revenue <b>30%</b></span></div></section>
        <div className="period-bar"><div><Icon name="calendar" size={18}/><strong>Periode</strong></div><select value={period.month} onChange={e=>changePeriod('month',e.target.value)}>{monthNames.map((m,i)=><option key={m} value={i+1}>{m}</option>)}</select><select value={period.year} onChange={e=>changePeriod('year',e.target.value)}>{years.map(y=><option value={y} key={y}>{y}</option>)}</select></div>
        <div className="target-user-grid">{rows.map((row)=><article className="panel sales-target-card" key={row.user.id}><div className="sales-target-head"><div className="user-cell"><Avatar name={row.user.name}/><div><strong>{row.user.name}</strong><span>{row.user.email}</span></div></div><div className="score-ring lg" style={{'--score':`${row.performance.score*3.6}deg`}}><span>{row.performance.score}%</span></div></div>
            <div className="target-metric-grid"><TargetMetric label="Dihubungi" actual={row.performance.actual.contacted} goal={row.performance.goals.contacted} progress={row.performance.progress.contacted}/><TargetMetric label="Meeting" actual={row.performance.actual.meetings} goal={row.performance.goals.meetings} progress={row.performance.progress.meetings}/><TargetMetric label="Proposal" actual={row.performance.actual.proposals} goal={row.performance.goals.proposals} progress={row.performance.progress.proposals}/><TargetMetric label="Deal" actual={row.performance.actual.deals} goal={row.performance.goals.deals} progress={row.performance.progress.deals}/><TargetMetric label="Revenue" actual={row.performance.actual.revenue} goal={row.performance.goals.revenue} progress={row.performance.progress.revenue} kind="money"/></div>
            <div className="workload-strip"><div><span>Active workload</span><strong>{row.performance.actual.active_companies} perusahaan</strong></div><div><span>Follow-up overdue</span><strong className={row.performance.actual.overdue_followups?'text-danger':''}>{row.performance.actual.overdue_followups}</strong></div>{canManage&&<button className="btn btn-soft" onClick={()=>setEditing(row)}><Icon name="edit" size={15}/>Atur target</button>}</div>
        </article>)}</div>
        {editing&&<TargetModal row={editing} period={period} onClose={()=>setEditing(null)}/>} 
    </AppLayout>;
}
