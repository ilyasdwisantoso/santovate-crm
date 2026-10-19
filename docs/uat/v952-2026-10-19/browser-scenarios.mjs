import { chromium } from './browser/node_modules/playwright-core/index.mjs';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
const root=process.cwd(),base='http://127.0.0.1:8095';
const runtime=JSON.parse(fs.readFileSync('runtime.json','utf8'));
const fixture=JSON.parse(fs.readFileSync('fixtures.json','utf8'));
const results=[],errors=[],httpErrors=[],externalBlocked=[],ids={legacy:fixture.legacyId};
const started=new Date().toISOString();
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',args:['--disable-background-networking','--no-first-run']});
const contexts=[];
function persist(){fs.writeFileSync('artifacts/results.json',JSON.stringify({sourceCommit:'c5c0539e771b7cca723402fab17fa7cd499995ec',started,browser:browser.version(),results,errors,httpErrors,externalBlocked,ids},null,2));}
async function context(mobile=false){
 const ctx=await browser.newContext({viewport:mobile?{width:390,height:844}:{width:1440,height:1000},isMobile:mobile,hasTouch:mobile,timezoneId:'Asia/Bangkok'});
 contexts.push(ctx);
 await ctx.route('**/*',route=>{const url=route.request().url();if(new URL(url).origin===base)return route.continue();externalBlocked.push(new URL(url).origin);return route.abort();});
 ctx.on('page',p=>{p.on('dialog',dialog=>dialog.accept());p.on('pageerror',error=>errors.push({url:p.url(),message:error.message}));p.on('response',r=>{if(r.status()>=400)httpErrors.push({status:r.status(),method:r.request().method(),url:r.url()});});p.setDefaultTimeout(45000);p.setDefaultNavigationTimeout(60000);});
 return ctx;
}
const ctx=await context(),page=await ctx.newPage();
async function ready(p){await p.locator('body').waitFor();await p.waitForLoadState('networkidle');}
async function goto(p,path){const r=await p.goto(base+path);await ready(p);return r;}
async function login(p,account='main'){
 await goto(p,'/login');await p.locator('input[type=email]').fill(fixture[account].email);await p.locator('input[type=password]').fill(runtime.loginPassword);
 await Promise.all([p.waitForURL('**/dashboard'),p.getByRole('button',{name:'Masuk ke CRM',exact:true}).click()]);await ready(p);
}
async function screenshot(p,name){await p.screenshot({path:'artifacts/'+name+'.png',fullPage:true});}
async function step(id,name,p,fn){
 const begin=Date.now();console.log('START '+id+' '+name);
 try{await fn();results.push({id,name,status:'PASS',seconds:+((Date.now()-begin)/1000).toFixed(2)});console.log('PASS '+id);}
 catch(error){results.push({id,name,status:'FAIL',seconds:+((Date.now()-begin)/1000).toFixed(2),error:error.message});console.log('FAIL '+id+' '+error.message.slice(0,400));try{await screenshot(p,'FAIL-'+id);fs.writeFileSync('artifacts/FAIL-'+id+'.txt',(await p.locator('body').innerText()).slice(0,18000));}catch{}}
 persist();
}
async function submitProspect(p){
 await Promise.all([p.waitForURL(/\/prospects\/\d+$/),p.getByRole('button',{name:'Buat Prospek',exact:true}).click()]);await ready(p);return Number(new URL(p.url()).pathname.split('/').pop());
}
async function assertProspectName(p,name){await p.locator('.prospect-hero h2').waitFor();assert.equal(await p.locator('.prospect-hero h2').innerText(),name);}
function inspect(){const result=spawnSync('C:/php85/php.exe',['inspect.php'],{cwd:root,encoding:'utf8',timeout:45000});assert.equal(result.status,0,result.stderr||result.stdout);return JSON.parse(result.stdout);}
function flag(enabled){fs.writeFileSync('flag.json',JSON.stringify({enabled}));}
await step('01','Login admin dan dashboard desktop',page,async()=>{await login(page);await screenshot(page,'01-dashboard-desktop');assert.match(await page.title(),/Dashboard/);});
await step('02','Prospect B2B sebelum migrasi tetap terbaca',page,async()=>{await goto(page,'/prospects/'+ids.legacy);await assertProspectName(page,'PT UAT Legacy B2B');await screenshot(page,'02-legacy-b2b');});
await step('03','Buat individu melalui form desktop',page,async()=>{
 await goto(page,'/prospects/create');await page.getByLabel('Jenis pelanggan').selectOption('individual');
 await page.getByLabel('Nama individu *').fill('UAT Individu Form');
 assert.equal(await page.getByLabel('Nama Perusahaan *').count(),0);
 await page.getByLabel('Telepon / WhatsApp').fill('081111100010');
 await page.getByLabel('Kota').fill('Bandung');await screenshot(page,'03-individual-form-desktop');
 ids.individual=await submitProspect(page);await assertProspectName(page,'UAT Individu Form');await screenshot(page,'04-individual-detail-desktop');
});
await step('04','Duplikasi memerlukan review dan menyimpan ID berbeda',page,async()=>{
 assert.ok(ids.individual);await goto(page,'/prospects/create');await page.getByLabel('Jenis pelanggan').selectOption('individual');
 await page.getByLabel('Nama individu *').fill('UAT Individu Berbeda');
 await page.getByLabel('Telepon / WhatsApp').fill('081111100010');
 await page.getByRole('button',{name:'Buat Prospek',exact:true}).click();
 await page.getByText(/Kandidat duplikat ditemukan/).first().waitFor();
 await page.getByRole('button',{name:'Periksa kandidat duplikat',exact:true}).click();
 await page.getByRole('link',{name:'UAT Individu Form',exact:true}).waitFor();
 await screenshot(page,'05-duplicate-manual-review');
 await page.getByRole('checkbox',{name:/Tetap simpan jika/}).check();
 ids.duplicate=await submitProspect(page);assert.notEqual(ids.duplicate,ids.individual);await assertProspectName(page,'UAT Individu Berbeda');
});
await step('05','Regresi pembuatan perusahaan B2B',page,async()=>{
 await goto(page,'/prospects/create');assert.equal(await page.getByLabel('Jenis pelanggan').inputValue(),'business');
 await page.getByLabel('Nama Perusahaan *').fill('PT UAT New B2B');await page.getByLabel('Kota').fill('Surabaya');
 ids.business=await submitProspect(page);await assertProspectName(page,'PT UAT New B2B');
});
await step('06','Review dan konversi WhatsApp menjadi individu',page,async()=>{
 await goto(page,'/whatsapp/leads');let card=page.locator('.wa951-card').filter({hasText:'UAT WhatsApp Individual'});
 await card.getByLabel('Jenis calon pelanggan').selectOption('individual');
 await card.getByLabel('Account Executive').selectOption(String(fixture.sales.id));
 await card.getByRole('button',{name:/Simpan review/}).click();await page.waitForLoadState('networkidle');
 card=page.locator('.wa951-card').filter({hasText:'UAT WhatsApp Individual'});
 await card.getByLabel('Nama individu *').fill('UAT WhatsApp Native');
 await screenshot(page,'06-whatsapp-convert-individual');
 await Promise.all([page.waitForURL(/\/prospects\/\d+$/),card.getByRole('button',{name:'Buat prospek B2C',exact:true}).click()]);
 await ready(page);ids.waIndividual=Number(new URL(page.url()).pathname.split('/').pop());await assertProspectName(page,'UAT WhatsApp Native');await screenshot(page,'07-whatsapp-individual-detail');
});
await step('07','Konversi WhatsApp B2B tetap memakai nama perusahaan',page,async()=>{
 await goto(page,'/whatsapp/leads');const card=page.locator('.wa951-card').filter({hasText:'UAT WhatsApp Business'});
 await card.getByLabel('Jenis calon pelanggan').selectOption('business');
 await card.getByLabel('Nama perusahaan *').fill('PT UAT WhatsApp B2B');
 await Promise.all([page.waitForURL(/\/prospects\/\d+$/),card.getByRole('button',{name:'Buat prospek B2B',exact:true}).click()]);
 await ready(page);ids.waBusiness=Number(new URL(page.url()).pathname.split('/').pop());await assertProspectName(page,'PT UAT WhatsApp B2B');
});
await step('08','Pencarian dan filter jenis pelanggan melalui UI',page,async()=>{
 await goto(page,'/prospects');await page.getByRole('button',{name:'Filter lainnya',exact:true}).click();
 await page.getByLabel('Jenis pelanggan').selectOption('individual');
 await page.getByRole('searchbox').fill('UAT Individu Form');await page.getByRole('button',{name:'Terapkan pencarian',exact:true}).click();
 await page.waitForURL(/entity_type=individual/);await ready(page);
 assert.match(await page.locator('.prospect-table tbody').innerText(),/UAT Individu Form/);
 assert.doesNotMatch(await page.locator('.prospect-table tbody').innerText(),/PT UAT/);await screenshot(page,'08-individual-search-filter');
});
await step('09','Jenis identitas terkunci saat edit',page,async()=>{
 assert.ok(ids.individual);await goto(page,'/prospects/'+ids.individual+'/edit');
 assert.equal(await page.getByLabel('Jenis pelanggan').isDisabled(),true);
 assert.equal(await page.getByLabel('Nama individu *').inputValue(),'UAT Individu Form');
});
async function quotation(id,pid,name){
 await goto(page,'/quotations/create?prospect_id='+pid);
 assert.equal(await page.getByLabel('Prospek *').inputValue(),String(pid));
 assert.equal(await page.getByLabel('Prospek *').locator('option:checked').innerText(),name);
 await page.getByLabel('Service / Product').fill('UAT Service '+id);
 await page.getByLabel('Selling Price').fill('1000000');
 await Promise.all([page.waitForURL(/\/quotations\/\d+$/),page.getByRole('button',{name:'Buat Quotation',exact:true}).click()]);await ready(page);
 const qid=Number(new URL(page.url()).pathname.split('/').pop());ids['quotation'+id]=qid;
 assert.equal(await page.locator('.commercial-hero h2').innerText(),name);
 if(await page.getByRole('button',{name:'Approve',exact:true}).count()){await page.getByRole('button',{name:'Approve',exact:true}).click();await page.getByRole('button',{name:'Mark as Sent',exact:true}).waitFor();}
 await page.getByRole('button',{name:'Mark as Sent',exact:true}).click();await page.getByRole('button',{name:'Accepted',exact:true}).waitFor();
 await page.getByRole('button',{name:'Accepted',exact:true}).click();await page.getByRole('link',{name:'Create Deal',exact:true}).waitFor();
 await goto(page,'/quotations/'+qid+'/print');assert.equal(await page.locator('.quote-print-client h2').innerText(),name);assert.doesNotMatch(await page.locator('body').innerText(),/Internal cost|Recommended price|Minimum price/);
 await screenshot(page,'09-quotation-'+id+'-print');await page.emulateMedia({media:'print'});await page.pdf({path:'artifacts/quotation-'+id+'.pdf',format:'A4',printBackground:true});await page.emulateMedia({media:'screen'});
}
async function dealInvoice(id,name){
 const qid=ids['quotation'+id];assert.ok(qid);
 await goto(page,'/deals/from-quotation/'+qid);assert.match(await page.locator('body').innerText(),new RegExp(name));
 await Promise.all([page.waitForURL(/\/deals\/\d+$/),page.getByRole('button',{name:'Create Deal',exact:true}).click()]);await ready(page);
 const did=Number(new URL(page.url()).pathname.split('/').pop());ids['deal'+id]=did;
 await goto(page,'/finance/invoices/create?deal='+did);
 assert.equal(await page.getByLabel('Deal *').inputValue(),String(did));
 assert.match(await page.getByLabel('Deal *').locator('option:checked').innerText(),new RegExp(name));
 await page.getByLabel('Subtotal *').fill('1000000');await page.getByLabel('Status awal').selectOption('issued');
 await Promise.all([page.waitForURL(/\/finance\/invoices\/\d+$/),page.getByRole('button',{name:'Simpan Invoice',exact:true}).click()]);await ready(page);
 const iid=Number(new URL(page.url()).pathname.split('/').pop());ids['invoice'+id]=iid;
 assert.match(await page.locator('body').innerText(),new RegExp(name));await screenshot(page,'10-invoice-'+id+'-detail');
 await goto(page,'/finance/invoices/'+iid+'/print');assert.equal(await page.locator('.finance-print-client h2').innerText(),name);
 await screenshot(page,'11-invoice-'+id+'-print');await page.emulateMedia({media:'print'});await page.pdf({path:'artifacts/invoice-'+id+'.pdf',format:'A4',printBackground:true});await page.emulateMedia({media:'screen'});
}
await step('10','Quotation B2C: create, accepted, tampilan dan PDF',page,async()=>{assert.ok(ids.individual);await quotation('B2C',ids.individual,'UAT Individu Form');});
await step('11','Deal dan invoice B2C: create, tampilan dan PDF',page,async()=>{await dealInvoice('B2C','UAT Individu Form');});
await step('12','Quotation B2B: create, accepted, tampilan dan PDF',page,async()=>{assert.ok(ids.business);await quotation('B2B',ids.business,'PT UAT New B2B');});
await step('13','Deal dan invoice B2B: create, tampilan dan PDF',page,async()=>{await dealInvoice('B2B','PT UAT New B2B');});
await step('14','Nama individu pada selector Opportunity',page,async()=>{
 await goto(page,'/opportunities/create?prospect_id='+ids.individual);
 assert.equal(await page.getByLabel(/Prospek/).locator('option:checked').innerText(),'UAT Individu Form');
});
await step('15','Smoke rendering Dashboard, Pipeline, Follow-up dan Finance',page,async()=>{
 for(const path of ['/dashboard','/pipeline','/follow-ups','/finance','/finance/commissions']){
   const r=await goto(page,path);assert.equal(r.status(),200,path);assert.doesNotMatch(await page.locator('body').innerText(),/Server Error|500 Internal Server Error/);
 }
 await screenshot(page,'12-finance-commissions');
});
await step('16','Batas tenant: detail pelanggan tenant lain ditolak',page,async()=>{
 const r=await goto(page,'/prospects/'+fixture.privateId);assert.equal(r.status(),403);assert.doesNotMatch(await page.locator('body').innerText(),/UAT Other Tenant Private/);
});
const fullCtx=await context(),fullPage=await fullCtx.newPage();
await step('17','Kuota penuh: staging tetap ada dan konversi ditolak',fullPage,async()=>{
 await login(fullPage,'full');await goto(fullPage,'/whatsapp/leads');
 const card=fullPage.locator('.wa951-card').filter({hasText:'UAT Quota Pending'});
 await card.getByLabel('Jenis calon pelanggan').selectOption('individual');
 await card.getByLabel('Nama individu *').fill('UAT Quota Pending Person');
 await card.getByRole('button',{name:'Buat prospek B2C',exact:true}).click();
 await card.getByRole('alert').filter({hasText:/Kapasitas/}).waitFor();await screenshot(fullPage,'13-quota-full-staging-retained');
 const state=inspect();assert.equal(state.prospects.filter(p=>p.org===fixture.full.org).length,1);
 assert.equal(state.contacts.find(c=>c.id===fixture.contactIds['UAT Quota Pending']).prospect_id,null);
 assert.equal(state.messages.filter(m=>m.organization_id===fixture.full.org&&m.prospect_id===null).length,1);
});
await step('18','Flag OFF: individu tetap terbaca/diedit, create baru tertutup',page,async()=>{
 flag(false);await goto(page,'/prospects/create');assert.equal(await page.getByLabel('Jenis pelanggan').isDisabled(),true);
 assert.equal(await page.getByLabel('Jenis pelanggan').inputValue(),'business');
 const blocked=await page.evaluate(async()=>{
  const token=document.querySelector('meta[name="csrf-token"]').content;
  const response=await fetch('/prospects',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({entity_type:'individual',individual_name:'UAT Blocked By Flag',fit_score:0,pain_score:0,contact_score:0,status:'baru',qualification_status:'new'})});
  const body=await response.json();return {status:response.status,fields:Object.keys(body.errors||{})};
 });
 assert.equal(blocked.status,422);assert.ok(blocked.fields.includes('entity_type'));
 await goto(page,'/prospects/'+ids.individual);await assertProspectName(page,'UAT Individu Form');
 await goto(page,'/prospects/'+ids.individual+'/edit');await page.getByLabel('Catatan Internal').fill('UAT edited while B2C flag disabled');
 await Promise.all([page.waitForURL(/\/prospects\/\d+$/),page.getByRole('button',{name:'Simpan Perubahan',exact:true}).click()]);await ready(page);
 await assertProspectName(page,'UAT Individu Form');
 await goto(fullPage,'/whatsapp/leads');const card=fullPage.locator('.wa951-card').filter({hasText:'UAT Quota Pending'});
 await card.getByLabel('Jenis calon pelanggan').selectOption('individual');
 await card.getByText(/Konversi B2C belum diaktifkan/).waitFor();assert.equal(await card.getByRole('button',{name:'Buat prospek B2C',exact:true}).count(),0);
 await screenshot(fullPage,'14-flag-off-staging');flag(true);
});
const mobileCtx=await context(true),mobile=await mobileCtx.newPage();
await step('19','Mobile 390px: login, daftar, detail dan create individu',mobile,async()=>{
 flag(true);await login(mobile);
 await goto(mobile,'/prospects');assert.ok((await mobile.locator('.mobile-prospect-list').innerText()).includes('UAT Individu Form'));
 await screenshot(mobile,'15-mobile-prospect-list');
 await goto(mobile,'/prospects/'+ids.individual);await assertProspectName(mobile,'UAT Individu Form');await screenshot(mobile,'16-mobile-individual-detail');
 await goto(mobile,'/prospects/create');await mobile.getByLabel('Jenis pelanggan').selectOption('individual');
 await mobile.getByLabel('Nama individu *').fill('UAT Mobile Individual');
 const widths=await mobile.evaluate(()=>({viewport:innerWidth,document:document.documentElement.scrollWidth}));assert.ok(widths.document<=widths.viewport+2,JSON.stringify(widths));
 await screenshot(mobile,'17-mobile-individual-form');ids.mobile=await submitProspect(mobile);await assertProspectName(mobile,'UAT Mobile Individual');
});
await step('20','Mobile: review WhatsApp dan jenis identitas linked',mobile,async()=>{
 await goto(mobile,'/whatsapp/leads?status=linked');await mobile.getByText('UAT WhatsApp Individual',{exact:true}).waitFor();
 const card=mobile.locator('.wa951-card').filter({hasText:'UAT WhatsApp Individual'});
 assert.equal(await card.getByLabel('Jenis calon pelanggan').inputValue(),'individual');assert.equal(await card.getByLabel('Jenis calon pelanggan').isDisabled(),true);
 const widths=await mobile.evaluate(()=>({viewport:innerWidth,document:document.documentElement.scrollWidth}));assert.ok(widths.document<=widths.viewport+2,JSON.stringify(widths));
 await screenshot(mobile,'18-mobile-whatsapp-linked');
});
await step('21','Postcondition database: identitas, consent, link dan data lama',page,async()=>{
 const state=inspect();fs.writeFileSync('artifacts/database-postconditions.json',JSON.stringify(state,null,2));
 for(const key of ['individual','duplicate','waIndividual','mobile']){
  assert.ok(ids[key],key);const p=state.prospects.find(p=>p.id===ids[key]);assert.equal(p.entity_type,'individual');assert.equal(p.company_name,null);assert.equal(p.company_key,null);assert.equal(p.whatsapp_opt_in_at,null);
 }
 assert.equal(state.prospects.find(p=>p.id===ids.legacy).company_name,'PT UAT Legacy B2B');
 assert.equal(state.contacts.find(c=>c.id===fixture.contactIds['UAT WhatsApp Individual']).prospect_id,ids.waIndividual);
 assert.equal(state.messages.find(m=>m.organization_id===fixture.main.org&&m.provider_message_id.endsWith('6281111100001')).prospect_id,ids.waIndividual);
 assert.equal(state.prospects.find(p=>p.id===ids.individual).notes,'UAT edited while B2C flag disabled');
 assert.equal(state.prospects.filter(p=>p.org===fixture.main.org).length,7);
});
await step('22','Tidak ada error JavaScript atau respons HTTP 5xx',page,async()=>{assert.deepEqual(errors,[]);assert.equal(httpErrors.filter(r=>r.status>=500).length,0);});
flag(true);persist();
console.log(JSON.stringify({summary:{pass:results.filter(x=>x.status==='PASS').length,fail:results.filter(x=>x.status==='FAIL').length,total:results.length},errors,httpErrors,externalBlocked}));
await browser.close();
