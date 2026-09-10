import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import { PriorityBadge } from '../../Components/Ui';
import { money } from '../../Utils/format';

export default function Pipeline({ columns, closed }) {
    const [dragged,setDragged]=useState(null);
    const move=(prospectId,status)=>router.patch(`/prospects/${prospectId}/status`,{status},{preserveScroll:true,preserveState:false});
    return <AppLayout title="Pipeline" subtitle="Geser peluang di desktop, atau ubah tahap langsung dari kartu saat memakai mobile." action={<Link href="/prospects/create" className="btn btn-primary"><Icon name="plus" size={17}/>Tambah Prospek</Link>}>
        <Head title="Pipeline"/>
        <section className="pipeline-summary"><div><span>Deal dimenangkan</span><strong>{closed.won}</strong></div><div><span>Tidak lanjut</span><strong>{closed.lost}</strong></div><p>Pipeline menggambarkan progres percakapan, bukan sekadar jumlah perusahaan yang disimpan.</p></section>
        <div className="kanban-board">
            {columns.map((col)=><section className="kanban-column" key={col.key} onDragOver={(e)=>e.preventDefault()} onDrop={()=>{if(dragged) move(dragged,col.key);setDragged(null);}}>
                <header><div><span className={`stage-dot stage-${col.key}`}/><strong>{col.label}</strong><b>{col.count}</b></div><small>{money(col.value)}</small></header>
                <div className="kanban-cards">{col.prospects.length?col.prospects.map((p)=><article className="kanban-card" key={p.id} draggable onDragStart={()=>setDragged(p.id)} onDragEnd={()=>setDragged(null)}>
                    <div className="kanban-card-top"><PriorityBadge priority={p.priority}/><span className="score-pill">{p.total_score}/9</span></div>
                    <Link href={`/prospects/${p.id}`}><h4>{p.company_name}</h4></Link><p>{[p.city,p.service].filter(Boolean).join(' · ')||'Data belum lengkap'}</p>
                    <div className="kanban-value"><span>Potensi</span><strong>{money(p.estimated_deal_value)}</strong></div>
                    <div className="kanban-owner"><span>{p.assigned_user?.name||'Belum diassign'}</span><select value={p.status} onChange={e=>move(p.id,e.target.value)}>{columns.map((c)=><option key={c.key} value={c.key}>{c.label}</option>)}</select></div>
                </article>):<div className="kanban-empty">Belum ada prospek</div>}</div>
            </section>)}
        </div>
    </AppLayout>;
}
