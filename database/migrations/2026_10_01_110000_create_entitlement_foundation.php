<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('subscriptions', 'entitlement_overrides')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->json('entitlement_overrides')->nullable()->after('total_amount');
            });
        }

        if (!Schema::hasColumn('business_configurations', 'entitlement_features')) {
            Schema::table('business_configurations', function (Blueprint $table) {
                $table->json('entitlement_features')->nullable()->after('follow_up_rules');
            });
        }

        if (!Schema::hasTable('entitlement_grants')) {
            Schema::create('entitlement_grants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('key', 100);
                $table->string('kind', 30)->default('limit');
                $table->string('operation', 30)->default('add');
                $table->bigInteger('integer_value')->nullable();
                $table->boolean('boolean_value')->nullable();
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('reference', 160)->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['organization_id','key','status']);
                $table->index(['subscription_id','status']);
                $table->index(['source_type','source_id']);
            });
        }

        $features = [
            'software-agency'=>['software_agency_workflow'],
            'logistics-freight'=>['logistics_workflow'],
            'distributor-b2b'=>['distributor_workflow'],
            'parfum-retail'=>['retail_workflow'],
        ];
        foreach ($features as $key => $value) {
            DB::table('business_configurations')->where('key',$key)->update([
                'entitlement_features'=>json_encode($value, JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('entitlement_grants')) Schema::drop('entitlement_grants');
        if (Schema::hasColumn('subscriptions','entitlement_overrides')) {
            Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('entitlement_overrides'));
        }
        if (Schema::hasColumn('business_configurations','entitlement_features')) {
            Schema::table('business_configurations', fn (Blueprint $table) => $table->dropColumn('entitlement_features'));
        }
    }
};
