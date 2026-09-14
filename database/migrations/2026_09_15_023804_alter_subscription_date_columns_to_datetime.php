<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY starts_at DATETIME NULL");
        DB::statement("ALTER TABLE subscriptions MODIFY ends_at DATETIME NULL");
        DB::statement("ALTER TABLE subscriptions MODIFY activated_at DATETIME NULL");
        DB::statement("ALTER TABLE subscriptions MODIFY cancelled_at DATETIME NULL");
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan ke TIMESTAMP
        // karena TIMESTAMP memiliki batas tahun sekitar 2038.
    }
};