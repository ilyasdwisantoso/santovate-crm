<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commercial_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number');
            $table->string('status', 30)->default('draft')->index();
            $table->string('currency', 3)->default('IDR');
            $table->date('issue_date');
            $table->date('due_date')->nullable()->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'status', 'due_date'], 'commercial_invoices_org_status_due_idx');
            $table->index(['deal_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 30)->default('manual');
            $table->string('reference_id')->unique();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('status', 30)->default('pending_verification')->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_channel', 60)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('sender_name')->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'created_at'], 'payment_tx_org_status_created_idx');
            $table->index(['commercial_invoice_id', 'status'], 'payment_tx_invoice_status_idx');
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('processed')->index();
            $table->decimal('amount', 18, 2);
            $table->string('reference_number')->nullable();
            $table->text('reason');
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->index(['organization_id', 'refunded_at']);
        });

        Schema::create('sales_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('rate', 8, 4)->default(25);
            $table->decimal('commissionable_value', 18, 2)->default(0);
            $table->decimal('potential_amount', 18, 2)->default(0);
            $table->decimal('earned_amount', 18, 2)->default(0);
            $table->decimal('approved_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('estimated')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'status'], 'sales_commissions_org_user_status_idx');
        });

        Schema::create('commission_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_refund_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->decimal('amount', 18, 2);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['organization_id', 'occurred_at']);
            $table->index(['sales_commission_id', 'entry_type']);
        });

        $now = now();
        DB::table('deals')
            ->where('status', 'won')
            ->orderBy('id')
            ->chunkById(200, function ($deals) use ($now) {
                foreach ($deals as $deal) {
                    $commissionable = (float) $deal->commissionable_value;
                    DB::table('sales_commissions')->updateOrInsert(
                        ['deal_id' => $deal->id],
                        [
                            'organization_id' => $deal->organization_id,
                            'user_id' => $deal->owner_id,
                            'rate' => 25,
                            'commissionable_value' => $commissionable,
                            'potential_amount' => round($commissionable * 0.25, 2),
                            'earned_amount' => 0,
                            'approved_amount' => 0,
                            'paid_amount' => 0,
                            'status' => 'estimated',
                            'last_recalculated_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_ledger_entries');
        Schema::dropIfExists('sales_commissions');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('commercial_invoices');
    }
};
