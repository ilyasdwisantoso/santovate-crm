<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_inbound_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $table->string('phone_normalized', 32);
            $table->string('wa_id', 64)->nullable();
            $table->string('profile_name')->nullable();
            $table->string('source_name', 60)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->text('source_url')->nullable();
            $table->json('referral')->nullable();
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_id')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'phone_normalized'], 'wa_inbound_contact_per_org_unique');
            $table->index(['organization_id', 'last_message_at'], 'wa_inbound_contacts_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbound_contacts');
    }
};
