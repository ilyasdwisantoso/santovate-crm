<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('business_configurations') && !Schema::hasColumn('business_configurations', 'theme')) {
            Schema::table('business_configurations', function (Blueprint $table) {
                $table->json('theme')->nullable()->after('description');
            });
        }

        // Fix the 2038 TIMESTAMP limitation on existing installations.
        if (Schema::hasTable('subscriptions')) {
            foreach (['starts_at', 'ends_at', 'activated_at', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    DB::statement("ALTER TABLE subscriptions MODIFY {$column} DATETIME NULL");
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_configurations') && Schema::hasColumn('business_configurations', 'theme')) {
            Schema::table('business_configurations', function (Blueprint $table) {
                $table->dropColumn('theme');
            });
        }

        // Dates intentionally remain DATETIME. Returning them to TIMESTAMP would
        // reintroduce the year-2038 limitation that caused the seeder failure.
    }
};
