<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Santovate CRM uses two tenant roles only: Administrator and Account Executive.
        // Existing Finance users become Account Executive. Finance management remains an Admin responsibility.
        DB::table('users')->where('role', 'finance')->update([
            'role' => 'sales',
            'updated_at' => now(),
        ]);

        // Explicitly keep the known existing account aligned even if its role had been changed manually.
        DB::table('users')->where('email', 'dinafinance@gmail.com')->update([
            'role' => 'sales',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: after the role model is simplified, there is no reliable
        // way to distinguish former Finance users from ordinary Account Executives.
    }
};
