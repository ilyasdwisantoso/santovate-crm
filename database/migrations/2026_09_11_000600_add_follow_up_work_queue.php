<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'last_outbound_at')) {
                $table->timestamp('last_outbound_at')->nullable()->index()->after('last_contact_at');
            }
            if (!Schema::hasColumn('prospects', 'last_customer_reply_at')) {
                $table->timestamp('last_customer_reply_at')->nullable()->index()->after('last_outbound_at');
            }
            if (!Schema::hasColumn('prospects', 'follow_up_snoozed_until')) {
                $table->timestamp('follow_up_snoozed_until')->nullable()->index()->after('next_follow_up_at');
            }
            if (!Schema::hasColumn('prospects', 'follow_up_count')) {
                $table->unsignedInteger('follow_up_count')->default(0)->after('follow_up_snoozed_until');
            }
            if (!Schema::hasColumn('prospects', 'last_follow_up_message')) {
                $table->text('last_follow_up_message')->nullable()->after('follow_up_count');
            }
        });

        if (!Schema::hasTable('follow_up_templates')) {
            Schema::create('follow_up_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('trigger_type', 40)->index();
                $table->unsignedTinyInteger('wait_days')->default(5);
                $table->text('message');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

        }

        DB::table('follow_up_templates')->updateOrInsert(
            ['key' => 'no_reply_default'],
            [
                'name' => 'Belum Ada Feedback',
                'trigger_type' => 'no_reply',
                'wait_days' => 5,
                'message' => "Halo Pak/Bu {contact_name}, saya {ae_name} dari Santovate Digital Solution. Izin follow up kembali terkait komunikasi sebelumnya dengan {company_name}. Apakah saat ini Bapak/Ibu masih berkenan mendiskusikan kebutuhan digitalisasi, CRM, website, atau business software di perusahaan? Jika berkenan, saya bisa kirim ringkasan singkat atau jadwalkan demo sekitar 15 menit. Terima kasih.",
                'is_active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('follow_up_templates')->updateOrInsert(
            ['key' => 'customer_replied_default'],
            [
                'name' => 'Customer Sudah Membalas',
                'trigger_type' => 'customer_replied',
                'wait_days' => 0,
                'message' => "Halo Pak/Bu {contact_name}, terima kasih atas responsnya. Saya {ae_name} dari Santovate Digital Solution akan menindaklanjuti kebutuhan {company_name}. Agar solusi yang kami siapkan lebih relevan, boleh saya tahu bagian proses atau kebutuhan digital yang saat ini paling ingin Bapak/Ibu perbaiki? Saya bisa siapkan contoh alur atau demo yang sesuai.",
                'is_active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Backfill agar data lama langsung ikut antrean tanpa mengubah histori yang ada.
        DB::table('prospects')->whereNull('last_outbound_at')->whereNotNull('last_contact_at')
            ->update(['last_outbound_at' => DB::raw('last_contact_at')]);
        DB::table('prospects')->whereNull('last_customer_reply_at')->whereNotNull('replied_at')
            ->update(['last_customer_reply_at' => DB::raw('replied_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_templates');
        Schema::table('prospects', function (Blueprint $table) {
            foreach (['last_outbound_at','last_customer_reply_at','follow_up_snoozed_until','follow_up_count','last_follow_up_message'] as $column) {
                if (Schema::hasColumn('prospects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
