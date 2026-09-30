<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // company_key remains useful for duplicate matching, but is no longer a hard unique constraint.
            // B2B accounts with identical names/cities must be allowed after an explicit duplicate override.
            $table->dropUnique('prospects_company_key_unique');
            $table->index('company_key', 'prospects_company_key_index');
            if (!Schema::hasColumn('prospects', 'qualification_status')) $table->string('qualification_status', 30)->default('new')->index()->after('status');
            if (!Schema::hasColumn('prospects', 'decision_maker_name')) $table->string('decision_maker_name')->nullable()->after('contact_position');
            if (!Schema::hasColumn('prospects', 'decision_maker_position')) $table->string('decision_maker_position')->nullable()->after('decision_maker_name');
            if (!Schema::hasColumn('prospects', 'estimated_budget')) $table->decimal('estimated_budget', 18, 2)->nullable()->after('estimated_deal_value');
            if (!Schema::hasColumn('prospects', 'expected_timeline')) $table->string('expected_timeline')->nullable()->after('estimated_budget');
            if (!Schema::hasColumn('prospects', 'target_go_live')) $table->date('target_go_live')->nullable()->after('expected_timeline');
            if (!Schema::hasColumn('prospects', 'urgency')) $table->string('urgency', 20)->nullable()->after('target_go_live');
            if (!Schema::hasColumn('prospects', 'probability')) $table->unsignedTinyInteger('probability')->default(10)->after('urgency');
            if (!Schema::hasColumn('prospects', 'next_action')) $table->string('next_action')->nullable()->after('probability');
        });

        DB::table('prospects')->whereIn('status', ['dihubungi'])->update(['qualification_status'=>'contacted']);
        DB::table('prospects')->whereIn('status', ['membalas','meeting','demo','proposal','negosiasi','deal'])->update(['qualification_status'=>'qualified']);
        DB::table('prospects')->whereIn('status', ['ditolak','tidak_cocok'])->update(['qualification_status'=>'unqualified']);
        DB::table('prospects')->whereIn('status', ['diriset'])->update(['probability'=>20]);
        DB::table('prospects')->whereIn('status', ['dihubungi','membalas'])->update(['probability'=>30]);
        DB::table('prospects')->where('status','meeting')->update(['probability'=>45]);
        DB::table('prospects')->where('status','demo')->update(['probability'=>55]);
        DB::table('prospects')->where('status','proposal')->update(['probability'=>70]);
        DB::table('prospects')->where('status','negosiasi')->update(['probability'=>85]);
        DB::table('prospects')->where('status','deal')->update(['probability'=>100]);

        Schema::table('prospect_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('prospect_activities', 'contact_person')) $table->string('contact_person')->nullable()->after('description');
            if (!Schema::hasColumn('prospect_activities', 'client_feedback')) $table->text('client_feedback')->nullable()->after('contact_person');
            if (!Schema::hasColumn('prospect_activities', 'objection')) $table->text('objection')->nullable()->after('client_feedback');
            if (!Schema::hasColumn('prospect_activities', 'next_action')) $table->string('next_action')->nullable()->after('objection');
            if (!Schema::hasColumn('prospect_activities', 'next_follow_up_at')) $table->dateTime('next_follow_up_at')->nullable()->index()->after('next_action');
        });

        if (!Schema::hasTable('prospect_assignment_histories')) {
            Schema::create('prospect_assignment_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'prospect_id']);
            });
        }

        if (!Schema::hasTable('opportunities')) {
            Schema::create('opportunities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('status', 20)->default('open')->index();
                $table->string('stage', 30)->default('qualification')->index();
                $table->text('business_problem')->nullable();
                $table->text('current_process')->nullable();
                $table->text('required_solution')->nullable();
                $table->text('required_features')->nullable();
                $table->unsignedInteger('estimated_users')->nullable();
                $table->decimal('budget', 18, 2)->nullable();
                $table->decimal('expected_value', 18, 2)->default(0);
                $table->unsignedTinyInteger('probability')->default(10);
                $table->date('target_go_live')->nullable();
                $table->string('decision_maker')->nullable();
                $table->text('decision_process')->nullable();
                $table->string('urgency', 20)->nullable();
                $table->string('next_action')->nullable();
                $table->dateTime('next_follow_up_at')->nullable()->index();
                $table->string('lost_reason')->nullable();
                $table->string('competitor')->nullable();
                $table->dateTime('recontact_at')->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status', 'stage']);
                $table->index(['owner_id', 'status']);
            });
        }

        if (!Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
                $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('quotation_number');
                $table->unsignedInteger('revision_number')->default(1);
                $table->string('quotation_type', 20)->default('project');
                $table->string('status', 30)->default('draft')->index();
                $table->string('pricing_type', 20)->default('standard');
                $table->string('currency', 3)->default('IDR');
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->string('discount_type', 20)->default('percent');
                $table->decimal('discount_value', 18, 2)->default(0);
                $table->decimal('discount_amount', 18, 2)->default(0);
                $table->decimal('tax_percent', 8, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('grand_total', 18, 2)->default(0);
                $table->text('payment_terms')->nullable();
                $table->string('project_timeline')->nullable();
                $table->date('valid_until')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('requires_approval')->default(false);
                $table->string('approval_status', 30)->default('not_required');
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('viewed_at')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->timestamps();
                $table->unique(['organization_id', 'quotation_number']);
                $table->index(['organization_id', 'status']);
            });
        }

        if (!Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('quantity', 12, 2)->default(1);
                $table->string('unit')->default('item');
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('discount_percent', 8, 2)->default(0);
                $table->decimal('line_total', 18, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('quotation_revisions')) {
            Schema::create('quotation_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason')->nullable();
                $table->json('snapshot');
                $table->timestamps();
                $table->unique(['quotation_id', 'revision_number']);
            });
        }

        if (!Schema::hasTable('deals')) {
            Schema::create('deals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
                $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('deal_number');
                $table->string('status', 20)->default('won')->index();
                $table->date('closing_date');
                $table->decimal('quoted_value', 18, 2)->default(0);
                $table->decimal('actual_deal_value', 18, 2)->default(0);
                $table->decimal('commissionable_value', 18, 2)->default(0);
                $table->string('contract_status', 30)->default('pending');
                $table->date('project_start_date')->nullable();
                $table->text('special_agreement')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['organization_id', 'deal_number']);
                $table->index(['organization_id', 'closing_date']);
            });
        }

        if (!Schema::hasTable('deal_documents')) {
            Schema::create('deal_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30)->default('contract');
                $table->string('reference_number')->nullable();
                $table->string('original_name');
                $table->string('file_path');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('deal_payment_schedules')) {
            Schema::create('deal_payment_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('sequence')->default(1);
                $table->string('label');
                $table->decimal('percentage', 8, 2)->nullable();
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('trigger_type', 30)->default('date');
                $table->date('due_date')->nullable();
                $table->string('status', 20)->default('scheduled')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['deal_id', 'sequence']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_payment_schedules');
        Schema::dropIfExists('deal_documents');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('quotation_revisions');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('prospect_assignment_histories');

        Schema::table('prospect_activities', function (Blueprint $table) {
            foreach (['contact_person','client_feedback','objection','next_action','next_follow_up_at'] as $column) {
                if (Schema::hasColumn('prospect_activities', $column)) $table->dropColumn($column);
            }
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_company_key_index');
            $table->unique('company_key', 'prospects_company_key_unique');
            foreach (['qualification_status','decision_maker_name','decision_maker_position','estimated_budget','expected_timeline','target_go_live','urgency','probability','next_action'] as $column) {
                if (Schema::hasColumn('prospects', $column)) $table->dropColumn($column);
            }
        });
    }
};
