<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('solution_catalog_items')) {
            Schema::create('solution_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('code', 80);
                $table->string('category', 120)->default('Custom Extension');
                $table->string('name', 180);
                $table->text('description')->nullable();
                $table->string('pricing_model', 30)->default('one_time');
                $table->string('unit', 50)->default('project');
                $table->decimal('internal_cost', 18, 2)->default(0);
                $table->decimal('recommended_price', 18, 2)->default(0);
                $table->decimal('minimum_price', 18, 2)->nullable();
                $table->json('dependencies')->nullable();
                $table->text('upsell_notes')->nullable();
                $table->text('sales_notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['organization_id', 'code']);
                $table->index(['organization_id', 'category', 'is_active']);
            });
        }

        if (!Schema::hasTable('opportunity_solution_item')) {
            Schema::create('opportunity_solution_item', function (Blueprint $table) {
                $table->id();
                $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('solution_catalog_item_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 12, 2)->default(1);
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->unique(['opportunity_id', 'solution_catalog_item_id'], 'opp_solution_unique');
            });
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_items', 'solution_catalog_item_id')) {
                $table->foreignId('solution_catalog_item_id')->nullable()->after('quotation_id')->constrained('solution_catalog_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('quotation_items', 'internal_cost_snapshot')) {
                $table->decimal('internal_cost_snapshot', 18, 2)->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('quotation_items', 'recommended_price_snapshot')) {
                $table->decimal('recommended_price_snapshot', 18, 2)->nullable()->after('internal_cost_snapshot');
            }
            if (!Schema::hasColumn('quotation_items', 'minimum_price_snapshot')) {
                $table->decimal('minimum_price_snapshot', 18, 2)->nullable()->after('recommended_price_snapshot');
            }
        });

        $templates = [
            ['code'=>'EXT-LOG-ORDER','category'=>'Logistics Extension','name'=>'Create Order','description'=>'Pembuatan order/shipment dari CRM sebagai awal workflow operasional logistics.','dependencies'=>[],'upsell_notes'=>'Biasanya dipasangkan dengan Shipment Tracking dan Customer Portal.'],
            ['code'=>'EXT-LOG-TRACK','category'=>'Logistics Extension','name'=>'Shipment Tracking','description'=>'Tracking shipment, status event, ETA, dan visibility perjalanan sesuai scope operasional client.','dependencies'=>['Create Order'],'upsell_notes'=>'Pertimbangkan Customer Portal, WhatsApp Notification, atau API/Aggregator Integration.'],
            ['code'=>'EXT-PORTAL','category'=>'Portal','name'=>'Customer Portal','description'=>'Portal customer untuk melihat order, shipment, tracking, atau dokumen yang diizinkan.','dependencies'=>['Shipment Tracking'],'upsell_notes'=>'Cocok untuk client B2B yang ingin self-service tracking.'],
            ['code'=>'INT-API','category'=>'Integration','name'=>'API Integration','description'=>'Integrasi API pihak ketiga seperti ERP, WMS, carrier, payment, atau sistem internal client.','dependencies'=>[],'upsell_notes'=>'Lakukan technical discovery sebelum menentukan harga final.'],
            ['code'=>'INT-AGG','category'=>'Integration','name'=>'Aggregator Integration','description'=>'Integrasi aggregator/carrier platform untuk sinkronisasi layanan, order, rate, atau tracking.','dependencies'=>[],'upsell_notes'=>'Harga bergantung provider, endpoint, autentikasi, volume, dan SLA integrasi.'],
            ['code'=>'AUTO-WA','category'=>'Automation','name'=>'WhatsApp Notification','description'=>'Notifikasi WhatsApp berbasis event seperti order dibuat, shipment berubah status, atau reminder.','dependencies'=>[],'upsell_notes'=>'Pastikan channel/provider WhatsApp dan template approval sudah jelas.'],
            ['code'=>'EXT-DASH','category'=>'Custom Extension','name'=>'Custom Dashboard / Report','description'=>'Dashboard, KPI, export, atau report khusus sesuai kebutuhan bisnis client.','dependencies'=>[],'upsell_notes'=>'Tentukan sumber data, filter, KPI, role, dan format export saat discovery.'],
            ['code'=>'EXT-WORKFLOW','category'=>'Custom Extension','name'=>'Custom Workflow / Approval','description'=>'Workflow, approval, status, SLA, atau rule bisnis tambahan di luar konfigurasi standar.','dependencies'=>[],'upsell_notes'=>'Petakan actor, trigger, status, rule, exception, dan audit trail.'],
        ];

        if (Schema::hasTable('organizations')) {
            DB::table('organizations')->select('id')->orderBy('id')->chunkById(100, function ($organizations) use ($templates) {
                foreach ($organizations as $organization) {
                    foreach ($templates as $index => $template) {
                        DB::table('solution_catalog_items')->updateOrInsert(
                            ['organization_id'=>$organization->id, 'code'=>$template['code']],
                            [
                                'category'=>$template['category'],
                                'name'=>$template['name'],
                                'description'=>$template['description'],
                                'pricing_model'=>'custom',
                                'unit'=>'project',
                                'internal_cost'=>0,
                                'recommended_price'=>0,
                                'minimum_price'=>null,
                                'dependencies'=>json_encode($template['dependencies']),
                                'upsell_notes'=>$template['upsell_notes'],
                                'sales_notes'=>'Isi internal cost, recommended selling price, dan minimum selling price sebelum dipakai sebagai price reference.',
                                'is_active'=>true,
                                'sort_order'=>$index + 1,
                                'created_at'=>now(),
                                'updated_at'=>now(),
                            ]
                        );
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_items', 'solution_catalog_item_id')) $table->dropConstrainedForeignId('solution_catalog_item_id');
            foreach (['internal_cost_snapshot','recommended_price_snapshot','minimum_price_snapshot'] as $column) {
                if (Schema::hasColumn('quotation_items', $column)) $table->dropColumn($column);
            }
        });
        Schema::dropIfExists('opportunity_solution_item');
        Schema::dropIfExists('solution_catalog_items');
    }
};
