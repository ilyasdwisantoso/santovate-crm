<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('company_key')->unique();
            $table->string('company_name')->index();
            $table->string('website')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('service')->nullable();
            $table->string('route')->nullable();
            $table->string('company_size')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('current_system')->nullable();
            $table->boolean('tracking_portal')->nullable();
            $table->text('pain_hypothesis')->nullable();

            $table->unsignedTinyInteger('fit_score')->default(0);
            $table->unsignedTinyInteger('pain_score')->default(0);
            $table->unsignedTinyInteger('contact_score')->default(0);
            $table->unsignedTinyInteger('total_score')->default(0)->index();
            $table->string('priority', 20)->default('rendah')->index();
            $table->string('status', 30)->default('baru')->index();

            $table->timestamp('last_contact_at')->nullable()->index();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamp('contacted_at')->nullable()->index();
            $table->timestamp('replied_at')->nullable()->index();
            $table->timestamp('meeting_at')->nullable()->index();
            $table->timestamp('demo_at')->nullable()->index();
            $table->timestamp('proposal_at')->nullable()->index();
            $table->timestamp('negotiation_at')->nullable()->index();
            $table->timestamp('deal_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();

            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('estimated_deal_value', 15, 2)->default(0);
            $table->decimal('actual_deal_value', 15, 2)->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
