<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('approval_scope', 20)->default('tenant'); // tenant|platform
                $table->string('type', 60);
                $table->nullableMorphs('subject');
                $table->string('status', 40)->default('pending');
                $table->string('title', 255);
                $table->text('summary')->nullable();
                $table->decimal('amount', 18, 2)->nullable();
                $table->json('metadata')->nullable();
                $table->text('decision_notes')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['approval_scope','status']);
                $table->index(['organization_id','status']);
                $table->index(['type','status']);
            });
        }

        if (!Schema::hasTable('platform_audit_logs')) {
            Schema::create('platform_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 100);
                $table->nullableMorphs('target');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['action','created_at']);
                $table->index(['organization_id','created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('approval_requests');
    }
};
