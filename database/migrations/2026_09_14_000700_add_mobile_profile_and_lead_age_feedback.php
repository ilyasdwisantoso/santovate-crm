<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('phone', 30)->nullable()->after('email'));
        }
        if (!Schema::hasColumn('users', 'job_title')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('job_title', 100)->nullable()->after('phone'));
        }
        if (!Schema::hasColumn('users', 'department')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('department', 100)->nullable()->after('job_title'));
        }
        if (!Schema::hasColumn('users', 'profile_initials')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('profile_initials', 2)->nullable()->after('department'));
        }
        if (!Schema::hasColumn('users', 'whatsapp_signature')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('whatsapp_signature', 500)->nullable()->after('profile_initials'));
        }
        if (!Schema::hasColumn('users', 'bio')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('bio')->nullable()->after('whatsapp_signature'));
        }

        if (!Schema::hasColumn('prospects', 'last_feedback_at')) {
            Schema::table('prospects', fn (Blueprint $table) => $table->timestamp('last_feedback_at')->nullable()->index()->after('last_follow_up_message'));
        }
        if (!Schema::hasColumn('prospects', 'last_feedback_status')) {
            Schema::table('prospects', fn (Blueprint $table) => $table->string('last_feedback_status', 40)->nullable()->after('last_feedback_at'));
        }
        if (!Schema::hasColumn('prospects', 'last_feedback_note')) {
            Schema::table('prospects', fn (Blueprint $table) => $table->text('last_feedback_note')->nullable()->after('last_feedback_status'));
        }

        if (Schema::hasTable('follow_up_templates')) {
            DB::table('follow_up_templates')->updateOrInsert(
                ['key' => 'lead_age_h3'],
                [
                    'name' => 'Lead H-3 Belum Ditindaklanjuti',
                    'trigger_type' => 'lead_age',
                    'wait_days' => 3,
                    'message' => "Halo Pak/Bu {contact_name}, saya {ae_name} dari Santovate Digital Solution. Saya ingin memperkenalkan solusi digital yang mungkin relevan untuk {company_name}, khususnya terkait {service}. Jika berkenan, saya bisa kirim ringkasan singkat dan contoh implementasi yang sesuai. Apakah saya boleh lanjutkan informasinya?",
                    'is_active' => true,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('follow_up_templates')) {
            DB::table('follow_up_templates')->where('key', 'lead_age_h3')->delete();
        }

        foreach (['last_feedback_note', 'last_feedback_status', 'last_feedback_at'] as $column) {
            if (Schema::hasColumn('prospects', $column)) {
                Schema::table('prospects', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        foreach (['bio', 'whatsapp_signature', 'profile_initials', 'department', 'job_title', 'phone'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
