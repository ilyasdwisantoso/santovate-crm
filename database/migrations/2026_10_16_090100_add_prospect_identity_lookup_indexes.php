<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->index(['organization_id', 'phone_normalized'], 'prospects_org_phone_index');
            $table->index(['organization_id', 'entity_type', 'updated_at', 'id'], 'prospects_org_entity_updated_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Native prospect identity is forward-only; use a reviewed forward migration for index changes.');
    }
};
