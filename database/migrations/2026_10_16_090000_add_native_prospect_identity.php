<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('entity_type', 20)->default('business');
            $table->string('individual_name')->nullable();
            $table->string('company_name')->nullable()->change();
            $table->string('company_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Native prospect identity is forward-only. Disable new individual creation; do not remove identity columns or customer data.');
    }
};
