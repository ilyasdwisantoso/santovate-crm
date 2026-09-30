<?php

return [
    'last_reviewed' => '2026-10-01',
    'sales_flow' => [
        ['key'=>'prospect','label'=>'Prospek','short'=>'Siapa yang sedang didekati','description'=>'Database perusahaan/PIC sebelum ada peluang komersial yang cukup jelas.'],
        ['key'=>'opportunity','label'=>'Opportunity','short'=>'Apa peluang yang bisa dijual','description'=>'Peluang nyata setelah kebutuhan, scope awal, nilai, probability, decision maker, dan next step mulai diketahui.'],
        ['key'=>'quotation','label'=>'Quotation','short'=>'Apa yang ditawarkan & berapa harganya','description'=>'Penawaran yang berisi extension/solution, harga jual, discount, timeline, dan payment terms.'],
        ['key'=>'deal','label'=>'Deal','short'=>'Apa yang benar-benar disepakati','description'=>'Dibuat setelah quotation Accepted dan menjadi sumber actual deal value serta payment schedule.'],
        ['key'=>'finance','label'=>'Finance','short'=>'Apa yang sudah ditagihkan & dibayar','description'=>'Invoice, verified payment, outstanding, refund, dan commission ledger.'],
    ],

    'solution_templates' => [
        ['code'=>'EXT-LOG-ORDER','category'=>'Logistics Extension','name'=>'Create Order','description'=>'Pembuatan order/shipment dari CRM sebagai awal workflow operasional logistics.','dependencies'=>[],'upsell_notes'=>'Biasanya dipasangkan dengan Shipment Tracking dan Customer Portal.'],
        ['code'=>'EXT-LOG-TRACK','category'=>'Logistics Extension','name'=>'Shipment Tracking','description'=>'Tracking shipment, status event, ETA, dan visibility perjalanan sesuai scope operasional client.','dependencies'=>['Create Order'],'upsell_notes'=>'Pertimbangkan Customer Portal, WhatsApp Notification, atau API/Aggregator Integration.'],
        ['code'=>'EXT-PORTAL','category'=>'Portal','name'=>'Customer Portal','description'=>'Portal customer untuk melihat order, shipment, tracking, atau dokumen yang diizinkan.','dependencies'=>['Shipment Tracking'],'upsell_notes'=>'Cocok untuk client B2B yang ingin self-service tracking.'],
        ['code'=>'INT-API','category'=>'Integration','name'=>'API Integration','description'=>'Integrasi API pihak ketiga seperti ERP, WMS, carrier, payment, atau sistem internal client.','dependencies'=>[],'upsell_notes'=>'Lakukan technical discovery sebelum menentukan harga final.'],
        ['code'=>'INT-AGG','category'=>'Integration','name'=>'Aggregator Integration','description'=>'Integrasi aggregator/carrier platform untuk sinkronisasi layanan, order, rate, atau tracking.','dependencies'=>[],'upsell_notes'=>'Harga bergantung provider, endpoint, autentikasi, volume, dan SLA integrasi.'],
        ['code'=>'AUTO-WA','category'=>'Automation','name'=>'WhatsApp Notification','description'=>'Notifikasi WhatsApp berbasis event seperti order dibuat, shipment berubah status, atau reminder.','dependencies'=>[],'upsell_notes'=>'Pastikan channel/provider WhatsApp dan template approval sudah jelas.'],
        ['code'=>'EXT-DASH','category'=>'Custom Extension','name'=>'Custom Dashboard / Report','description'=>'Dashboard, KPI, export, atau report khusus sesuai kebutuhan bisnis client.','dependencies'=>[],'upsell_notes'=>'Tentukan sumber data, filter, KPI, role, dan format export saat discovery.'],
        ['code'=>'EXT-WORKFLOW','category'=>'Custom Extension','name'=>'Custom Workflow / Approval','description'=>'Workflow, approval, status, SLA, atau rule bisnis tambahan di luar konfigurasi standar.','dependencies'=>[],'upsell_notes'=>'Petakan actor, trigger, status, rule, exception, dan audit trail.'],
    ],

    'concepts' => [
        ['key'=>'opportunity','title'=>'Opportunity','plain'=>'Peluang penjualan/project yang sudah punya kebutuhan nyata.','example'=>'Client CRM Logistics meminta Create Order + Tracking + Customer Portal.','avoid'=>'Perusahaan yang baru masuk database masih Prospek, belum Opportunity.'],
        ['key'=>'expected_value','title'=>'Expected Value','plain'=>'Estimasi nilai jual jika opportunity berhasil.','example'=>'Gunakan estimasi selling price, bukan internal cost.','avoid'=>'Bukan revenue dan tidak harus sama dengan quotation final.'],
        ['key'=>'weighted_pipeline','title'=>'Weighted Pipeline','plain'=>'Expected Value × Probability.','example'=>'Rp100 juta × 60% = Rp60 juta weighted pipeline.','avoid'=>'Bukan uang yang sudah diterima.'],
        ['key'=>'quotation_item','title'=>'Quotation Item','plain'=>'Komponen solution/extension yang benar-benar ditawarkan ke client.','example'=>'Create Order, Shipment Tracking, Customer Portal, API Integration.','avoid'=>'Jangan memasukkan internal cost ke dokumen client.'],
        ['key'=>'outstanding','title'=>'Outstanding','plain'=>'Sisa invoice yang belum dibayar client.','example'=>'Invoice Rp30 juta, net paid Rp10 juta → outstanding Rp20 juta.','avoid'=>'Bukan seluruh Deal bila belum semua nilai Deal sudah di-invoice.'],
        ['key'=>'potential_commission','title'=>'Potential Commission','plain'=>'Maksimum potensi komisi dari commissionable value.','example'=>'Belum menjadi hak payout karena client mungkin belum membayar.','avoid'=>'Jangan disamakan dengan earned.'],
        ['key'=>'earned_commission','title'=>'Earned Commission','plain'=>'Komisi yang terbentuk dari pembayaran client yang sudah terverifikasi.','example'=>'Naik mengikuti net collection; refund dapat menurunkannya.','avoid'=>'Belum berarti sudah dibayar ke Sales.'],
        ['key'=>'commission_ledger','title'=>'Commission Ledger','plain'=>'Audit trail perubahan komisi Sales.','example'=>'Earned → Approved → Paid, termasuk adjustment/refund.','avoid'=>'Bukan daftar pembayaran client.'],
    ],
];
