<?php

namespace Database\Seeders;

use App\Models\BusinessConfiguration;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\BusinessConfigurationService;
use Illuminate\Database\Seeder;

class SaasFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['key'=>'starter','name'=>'Starter','description'=>'CRM inti untuk tim kecil.','monthly_price'=>199000,'annual_price'=>1990000,'user_limit'=>3,'prospect_limit'=>1000,'features'=>['crm','pipeline','followup','products'],'sort_order'=>1],
            ['key'=>'growth','name'=>'Growth','description'=>'Untuk tim sales yang membutuhkan automation dan reporting.','monthly_price'=>399000,'annual_price'=>3990000,'user_limit'=>10,'prospect_limit'=>5000,'features'=>['crm','pipeline','followup','products','whatsapp_api','campaigns','imports','targets'],'sort_order'=>2],
            ['key'=>'scale','name'=>'Scale','description'=>'Untuk operasi multi-user dengan konfigurasi lebih besar.','monthly_price'=>799000,'annual_price'=>7990000,'user_limit'=>30,'prospect_limit'=>25000,'features'=>['crm','pipeline','followup','products','whatsapp_api','campaigns','imports','targets','advanced_config'],'sort_order'=>3],
        ];
        foreach ($plans as $plan) SubscriptionPlan::updateOrCreate(['key'=>$plan['key']],$plan+['is_active'=>true]);

        $configs = [
            'software-agency'=>[
                'name'=>'Software & Agency','industry'=>'Technology / Services','description'=>'Discovery, demo, proposal, negotiation, project won.','monthly_addon_price'=>75000,'annual_addon_price'=>750000,'sort_order'=>1,
                'pipeline'=>$this->pipeline(['Lead','Qualified','Contacted','Replied','Discovery','Demo','Proposal','Negotiation','Won']),
                'terminology'=>['prospect'=>'Prospect','deal'=>'Project','sales'=>'Account Executive'],
                'follow_up_rules'=>['lead_age_days'=>3,'no_reply_days'=>5],
                'demo_templates'=>$this->templates('solusi digital'),
            ],
            'logistics-freight'=>[
                'name'=>'Logistics & Freight','industry'=>'Logistics','description'=>'Lead, meeting, quotation, negotiation, shipment account.','monthly_addon_price'=>125000,'annual_addon_price'=>1250000,'sort_order'=>2,
                'pipeline'=>$this->pipeline(['Lead','Researched','Contacted','Replied','Meeting','Needs Analysis','Quotation','Negotiation','Won']),
                'terminology'=>['prospect'=>'Shipper Account','deal'=>'Shipment Opportunity','sales'=>'Sales / AE'],
                'follow_up_rules'=>['lead_age_days'=>3,'no_reply_days'=>4],
                'demo_templates'=>$this->templates('layanan logistics dan freight'),
            ],
            'distributor-b2b'=>[
                'name'=>'Distributor & B2B Sales','industry'=>'Distribution','description'=>'Account list, quotation, territory, repeat-order workflow.','monthly_addon_price'=>100000,'annual_addon_price'=>1000000,'sort_order'=>3,
                'pipeline'=>$this->pipeline(['Lead','Qualified','Contacted','Replied','Meeting','Product Trial','Quotation','Negotiation','Order']),
                'terminology'=>['prospect'=>'Account','deal'=>'Order Opportunity','sales'=>'Sales Representative'],
                'follow_up_rules'=>['lead_age_days'=>2,'no_reply_days'=>4],
                'demo_templates'=>$this->templates('produk dan penawaran B2B'),
            ],
            'parfum-retail'=>[
                'name'=>'Parfum & Retail','industry'=>'Retail / Fragrance','description'=>'Lead sampai order dan repeat order dengan katalog produk.','monthly_addon_price'=>50000,'annual_addon_price'=>500000,'sort_order'=>4,
                'pipeline'=>$this->pipeline(['Lead Baru','Qualified','Dihubungi','Tertarik','Tester / Sample','Follow Up','Penawaran','Closing','Order']),
                'terminology'=>['prospect'=>'Customer','deal'=>'Order','sales'=>'Sales / Reseller'],
                'follow_up_rules'=>['lead_age_days'=>1,'no_reply_days'=>3],
                'demo_templates'=>[
                    ['trigger_type'=>'lead_age','name'=>'Perkenalan Produk','wait_days'=>1,'message'=>'Halo Kak {contact_name}, saya {ae_first_name} dari {business_name}. Kakak sempat tertarik dengan {product_name} {product_variant}. Saat ini harganya {product_price}. Boleh saya kirim foto dan detail aromanya?','body_parameters'=>['contact_name','product_name','product_variant','product_price']],
                    ['trigger_type'=>'no_reply','name'=>'Follow-up Produk','wait_days'=>3,'message'=>'Halo Kak {contact_name}, izin follow-up {product_name}. Kalau masih mencari parfum untuk dipakai sendiri atau reseller, saya bisa bantu rekomendasikan varian dan penawaran yang cocok.','body_parameters'=>['contact_name','product_name']],
                    ['trigger_type'=>'customer_replied','name'=>'Balasan Customer','wait_days'=>0,'message'=>'Siap Kak {contact_name}. Boleh saya tahu karakter aroma yang Kakak suka atau budget yang dicari? Saya bantu pilihkan varian yang paling sesuai.','body_parameters'=>['contact_name']],
                ],
                'demo_products'=>[
                    ['sku'=>'SV-OD-30','name'=>'Santovate Oud','variant'=>'30 ml','price'=>199000,'description'=>'Karakter woody-oud elegan.'],
                    ['sku'=>'SV-BL-30','name'=>'Santovate Blue','variant'=>'30 ml','price'=>179000,'description'=>'Fresh aromatic untuk daily wear.'],
                    ['sku'=>'SV-MK-50','name'=>'Santovate Musk','variant'=>'50 ml','price'=>249000,'description'=>'Clean musk dengan karakter lembut.'],
                ],
            ],
        ];
        foreach ($configs as $key=>$cfg) BusinessConfiguration::updateOrCreate(['key'=>$key],$cfg+['is_active'=>true]);

        $legacy=Organization::query()->where('slug','santovate-internal')->first();
        if ($legacy) {
            $plan=SubscriptionPlan::where('key','scale')->firstOrFail();
            $config=BusinessConfiguration::where('key','software-agency')->firstOrFail();
            $subscription=Subscription::updateOrCreate(
                ['organization_id'=>$legacy->id,'status'=>'active'],
                ['subscription_plan_id'=>$plan->id,'business_configuration_id'=>$config->id,'billing_cycle'=>'annual','base_amount'=>0,'configuration_amount'=>0,'total_amount'=>0,'starts_at'=>now(),'ends_at'=>now()->addYears(20),'activated_at'=>now()]
            );
            app(BusinessConfigurationService::class)->activate($legacy,$config);
        }
    }

    private function pipeline(array $labels): array
    {
        $keys=['baru','diriset','dihubungi','membalas','meeting','demo','proposal','negosiasi','deal'];
        return collect($keys)->map(fn($key,$i)=>['key'=>$key,'label'=>$labels[$i]??ucfirst($key)])->all();
    }

    private function templates(string $service): array
    {
        return [
            ['trigger_type'=>'lead_age','name'=>'Initial Outreach','wait_days'=>3,'message'=>'Halo {contact_name}, saya {ae_first_name} dari {business_name}. Kami membantu bisnis dengan '.$service.'. Apakah boleh saya kirim overview singkat yang relevan untuk {company_name}?','body_parameters'=>['contact_name','ae_first_name','business_name','company_name']],
            ['trigger_type'=>'no_reply','name'=>'Follow-up Belum Ada Respons','wait_days'=>5,'message'=>'Halo {contact_name}, izin follow-up kembali terkait '.$service.'. Jika masih relevan, saya siap bantu kirim ringkasan atau jadwalkan demo singkat.','body_parameters'=>['contact_name']],
            ['trigger_type'=>'customer_replied','name'=>'Customer Sudah Membalas','wait_days'=>0,'message'=>'Halo {contact_name}, terima kasih atas responsnya. Boleh saya tahu kebutuhan atau kendala utama yang ingin dibenahi di {company_name}?','body_parameters'=>['contact_name','company_name']],
        ];
    }
}
