import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Components/Icon';

const nav = [
    { href:'/platform', label:'Overview', icon:'home', match:(u)=>u==='/platform' || u.startsWith('/platform?') },
    { href:'/platform/clients', label:'Clients', icon:'users', match:(u)=>u.startsWith('/platform/clients') },
    { href:'/platform/configurations', label:'Business Configurations', icon:'spark', match:(u)=>u.startsWith('/platform/configurations') },
    { href:'/platform/addons', label:'Subscription Add-ons', icon:'briefcase', match:(u)=>u.startsWith('/platform/addons') },
    { href:'/platform/approvals', label:'Approvals', icon:'check', match:(u)=>u.startsWith('/platform/approvals') },
];

export default function PlatformLayout({ title, subtitle, children, action }) {
    const { auth } = usePage().props;
    const url = usePage().url;
    const [open,setOpen] = useState(false);
    const logout = () => router.post('/logout');

    return <div className="platform-shell">
        <Head title={`${title} · Santovate Platform`}/>
        <aside className={`platform-sidebar ${open?'open':''}`}>
            <div className="platform-brand">
                <img src="/images/brand/santovate-crm-logo.png" alt="Santovate"/>
                <div><strong>Santovate</strong><small>Platform Owner</small></div>
                <button className="platform-close" onClick={()=>setOpen(false)}><Icon name="close" size={18}/></button>
            </div>
            <div className="platform-owner-chip"><span>OWNER CONTROL CENTER</span><strong>{auth.user?.name}</strong><small>{auth.user?.email}</small></div>
            <nav>{nav.map(item=><Link key={item.href} href={item.href} className={item.match(url)?'active':''} onClick={()=>setOpen(false)}><Icon name={item.icon} size={18}/><span>{item.label}</span></Link>)}</nav>
            <div className="platform-sidebar-foot">
                <Link href="/dashboard" className="platform-crm-link"><Icon name="briefcase" size={17}/><span>CRM Internal</span></Link>
                <button onClick={logout}><Icon name="logout" size={17}/><span>Keluar</span></button>
            </div>
        </aside>
        {open&&<button className="platform-backdrop" onClick={()=>setOpen(false)} aria-label="Tutup menu"/>}
        <main className="platform-main">
            <header className="platform-topbar">
                <button className="platform-menu" onClick={()=>setOpen(true)}><Icon name="menu" size={19}/></button>
                <div><span>Platform / Owner</span><h1>{title}</h1>{subtitle&&<p>{subtitle}</p>}</div>
                {action&&<div>{action}</div>}
            </header>
            <div className="platform-content">{children}</div>
        </main>
    </div>;
}
