import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email:'', password:'', remember:true });
    const [show, setShow] = useState(false);
    const submit = (e) => { e.preventDefault(); post('/login'); };

    return <>
        <Head title="Login"/>
        <main className="login-page">
            <section className="login-brand-panel">
                <div className="login-brand"><span className="brand-mark lg">S</span><div><strong>Santovate</strong><small>Digital Solution</small></div></div>
                <div className="login-story">
                    <span className="eyebrow"><Icon name="spark" size={15}/> Account Executive workspace</span>
                    <h1>Pipeline lebih jelas.<br/><em>Follow-up lebih disiplin.</em></h1>
                    <p>CRM internal untuk membantu tim Santovate mengubah database prospek menjadi percakapan, proposal, dan revenue yang terukur.</p>
                    <div className="login-proof-grid">
                        <article><strong>01</strong><span>Prioritaskan prospek terbaik</span></article>
                        <article><strong>02</strong><span>Pantau follow-up & pipeline</span></article>
                        <article><strong>03</strong><span>Kejar target berbasis outcome</span></article>
                    </div>
                </div>
                <p className="login-foot">Santovate CRM · Internal workspace</p>
            </section>
            <section className="login-form-panel">
                <div className="login-mobile-brand"><span className="brand-mark">S</span><div><strong>Santovate</strong><small>CRM Account Executive</small></div></div>
                <form className="login-card" onSubmit={submit}>
                    <div className="login-card-head"><span className="login-icon"><Icon name="briefcase" size={20}/></span><h2>Selamat datang kembali</h2><p>Masuk untuk melanjutkan aktivitas Account Executive Anda.</p></div>
                    <label className="field"><span>Email</span><div className={`input-icon ${errors.email?'error':''}`}><Icon name="mail" size={18}/><input type="email" value={data.email} onChange={(e)=>setData('email',e.target.value)} placeholder="nama@santovate.com" autoComplete="email" autoFocus/></div>{errors.email&&<small className="field-error">{errors.email}</small>}</label>
                    <label className="field"><span>Password</span><div className={`input-icon password-input ${errors.password?'error':''}`}><Icon name="target" size={18}/><input type={show?'text':'password'} value={data.password} onChange={(e)=>setData('password',e.target.value)} placeholder="Masukkan password" autoComplete="current-password"/><button type="button" onClick={()=>setShow(!show)}><Icon name={show?'eyeOff':'eye'} size={18}/></button></div>{errors.password&&<small className="field-error">{errors.password}</small>}</label>
                    <label className="remember-row"><input type="checkbox" checked={data.remember} onChange={(e)=>setData('remember',e.target.checked)}/><span>Ingat saya di perangkat ini</span></label>
                    <button className="btn btn-primary btn-block btn-lg" disabled={processing}>{processing?'Memproses...':'Masuk ke CRM'}<Icon name="chevron" size={18}/></button>
                    <div className="security-note"><span>●</span> Akses khusus tim internal Santovate</div>
                </form>
            </section>
        </main>
    </>;
}
