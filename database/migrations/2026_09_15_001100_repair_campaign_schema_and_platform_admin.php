<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_platform_admin')->default(false)->index();
            });
        }

        if (!Schema::hasTable('follow_up_templates')) {
            Schema::create('follow_up_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('business_configuration_id')->nullable()->index();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('trigger_type', 40)->index();
                $table->unsignedTinyInteger('wait_days')->default(5);
                $table->text('message');
                $table->string('header_type', 20)->default('none');
                $table->text('image_url')->nullable();
                $table->string('meta_template_name')->nullable();
                $table->string('meta_language', 20)->default('id');
                $table->string('meta_status', 30)->default('draft')->index();
                $table->string('meta_category', 30)->default('marketing');
                $table->json('body_parameters')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        } else {
            $missing = [];
            foreach ([
                'organization_id','business_configuration_id','header_type','image_url','meta_template_name',
                'meta_language','meta_status','meta_category','body_parameters'
            ] as $column) {
                if (!Schema::hasColumn('follow_up_templates', $column)) $missing[] = $column;
            }
            if ($missing) {
                Schema::table('follow_up_templates', function (Blueprint $table) use ($missing) {
                    if (in_array('organization_id', $missing, true)) $table->unsignedBigInteger('organization_id')->nullable()->index();
                    if (in_array('business_configuration_id', $missing, true)) $table->unsignedBigInteger('business_configuration_id')->nullable()->index();
                    if (in_array('header_type', $missing, true)) $table->string('header_type', 20)->default('none');
                    if (in_array('image_url', $missing, true)) $table->text('image_url')->nullable();
                    if (in_array('meta_template_name', $missing, true)) $table->string('meta_template_name')->nullable();
                    if (in_array('meta_language', $missing, true)) $table->string('meta_language', 20)->default('id');
                    if (in_array('meta_status', $missing, true)) $table->string('meta_status', 30)->default('draft')->index();
                    if (in_array('meta_category', $missing, true)) $table->string('meta_category', 30)->default('marketing');
                    if (in_array('body_parameters', $missing, true)) $table->json('body_parameters')->nullable();
                });
            }
        }

        if (Schema::hasTable('prospects')) {
            $missing = [];
            foreach (['phone_normalized','whatsapp_status','whatsapp_id','whatsapp_verified_at','whatsapp_opt_in_at','whatsapp_opt_out_at','whatsapp_last_error'] as $column) {
                if (!Schema::hasColumn('prospects', $column)) $missing[] = $column;
            }
            if ($missing) {
                Schema::table('prospects', function (Blueprint $table) use ($missing) {
                    if (in_array('phone_normalized', $missing, true)) $table->string('phone_normalized', 32)->nullable()->index();
                    if (in_array('whatsapp_status', $missing, true)) $table->string('whatsapp_status', 30)->default('unknown')->index();
                    if (in_array('whatsapp_id', $missing, true)) $table->string('whatsapp_id')->nullable();
                    if (in_array('whatsapp_verified_at', $missing, true)) $table->timestamp('whatsapp_verified_at')->nullable();
                    if (in_array('whatsapp_opt_in_at', $missing, true)) $table->timestamp('whatsapp_opt_in_at')->nullable();
                    if (in_array('whatsapp_opt_out_at', $missing, true)) $table->timestamp('whatsapp_opt_out_at')->nullable();
                    if (in_array('whatsapp_last_error', $missing, true)) $table->text('whatsapp_last_error')->nullable();
                });
            }
        }


        if (!Schema::hasTable('whatsapp_channels')) {
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
        }

        if (!Schema::hasTable('whatsapp_campaigns')) {
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
        }

        if (!Schema::hasTable('whatsapp_campaign_recipients')) {
            Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('whatsapp_campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
                $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
                $table->string('status', 30)->default('queued')->index();
                $table->string('provider_message_id')->nullable()->index();
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['whatsapp_campaign_id','prospect_id'], 'wa_campaign_prospect_unique');
            });
        }


        if (!Schema::hasTable('whatsapp_messages')) {
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
        }
    }

    public function down(): void
    {
        // Repair migration is intentionally non-destructive.
        // Existing campaign/user columns are retained on rollback to prevent data loss.
    }
};
