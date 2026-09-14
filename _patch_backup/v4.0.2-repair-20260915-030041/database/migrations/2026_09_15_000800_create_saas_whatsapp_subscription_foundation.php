<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price')->default(0);
            $table->unsignedBigInteger('annual_price')->default(0);
            $table->unsignedInteger('user_limit')->default(3);
            $table->unsignedInteger('prospect_limit')->default(1000);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('business_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_addon_price')->default(0);
            $table->unsignedBigInteger('annual_addon_price')->default(0);
            $table->json('pipeline')->nullable();
            $table->json('terminology')->nullable();
            $table->json('follow_up_rules')->nullable();
            $table->json('demo_templates')->nullable();
            $table->json('demo_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('business_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'role']);
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone_normalized', 32)->nullable()->index()->after('phone');
            $table->string('whatsapp_status', 30)->default('unknown')->index()->after('phone_normalized');
            $table->string('whatsapp_id')->nullable()->after('whatsapp_status');
            $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_id');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_verified_at');
            $table->timestamp('whatsapp_opt_out_at')->nullable()->after('whatsapp_opt_in_at');
            $table->text('whatsapp_last_error')->nullable()->after('whatsapp_opt_out_at');
            $table->index(['organization_id', 'status']);
        });

        Schema::table('follow_up_templates', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_configuration_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('header_type', 20)->default('none')->after('message');
            $table->text('image_url')->nullable()->after('header_type');
            $table->string('meta_template_name')->nullable()->after('image_url');
            $table->string('meta_language', 20)->default('id')->after('meta_template_name');
            $table->string('meta_status', 30)->default('draft')->after('meta_language');
            $table->string('meta_category', 30)->default('marketing')->after('meta_status');
            $table->json('body_parameters')->nullable()->after('meta_category');
            $table->index(['organization_id', 'trigger_type']);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::table('sales_targets', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'year', 'month']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('variant')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->text('image_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'sku']);
        });

        Schema::create('prospect_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['prospect_id', 'product_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('base_amount')->default(0);
            $table->unsignedBigInteger('configuration_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->dateTime('starts_at')->nullable();
$table->dateTime('ends_at')->nullable();
$table->dateTime('activated_at')->nullable();
$table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('ipaymu');
            $table->string('reference_id')->unique();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_channel')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('meta_cloud');
            $table->string('phone_number_id')->nullable();
            $table->string('waba_id')->nullable();
            $table->text('access_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_template_id')->nullable()->constrained('follow_up_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('status', 30)->default('draft')->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('queued')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_campaign_id', 'prospect_id'], 'wa_campaign_prospect_unique');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('follow_up_template_id')->nullable()->constrained('follow_up_templates')->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained('whatsapp_campaign_recipients')->nullOnDelete();
            $table->string('direction', 20)->default('outbound');
            $table->string('type', 30)->default('text');
            $table->string('status', 30)->default('queued')->index();
            $table->string('to_phone', 32)->nullable();
            $table->string('from_phone', 32)->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->text('body')->nullable();
            $table->text('media_url')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        $legacyId = DB::table('organizations')->insertGetId([
            'name' => 'Santovate Internal',
            'slug' => 'santovate-internal',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->whereNull('organization_id')->update(['organization_id' => $legacyId]);
        DB::table('prospects')->whereNull('organization_id')->update(['organization_id' => $legacyId]);
        DB::table('follow_up_templates')->whereNull('organization_id')->update(['organization_id' => $legacyId]);
        DB::table('import_batches')->whereNull('organization_id')->update(['organization_id' => $legacyId]);
        DB::table('sales_targets')->whereNull('organization_id')->update(['organization_id' => $legacyId]);

        $rows = DB::table('prospects')->select('id', 'company_key', 'phone')->get();
        foreach ($rows as $row) {
            $digits = preg_replace('/\D+/', '', (string) $row->phone);
            if ($digits && str_starts_with($digits, '0')) $digits = '62'.substr($digits, 1);
            elseif ($digits && str_starts_with($digits, '8')) $digits = '62'.$digits;
            DB::table('prospects')->where('id', $row->id)->update([
                'company_key' => $legacyId.'-'.ltrim((string) $row->company_key, '-'),
                'phone_normalized' => $digits ?: null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_channels');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('prospect_product');
        Schema::dropIfExists('products');

        Schema::table('sales_targets', function (Blueprint $table) { $table->dropConstrainedForeignId('organization_id'); });
        Schema::table('import_batches', function (Blueprint $table) { $table->dropConstrainedForeignId('organization_id'); });
        Schema::table('follow_up_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_configuration_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['header_type','image_url','meta_template_name','meta_language','meta_status','meta_category','body_parameters']);
        });
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['phone_normalized','whatsapp_status','whatsapp_id','whatsapp_verified_at','whatsapp_opt_in_at','whatsapp_opt_out_at','whatsapp_last_error']);
        });
        Schema::table('users', function (Blueprint $table) { $table->dropConstrainedForeignId('organization_id'); });

        Schema::dropIfExists('organizations');
        Schema::dropIfExists('business_configurations');
        Schema::dropIfExists('subscription_plans');
    }
};
