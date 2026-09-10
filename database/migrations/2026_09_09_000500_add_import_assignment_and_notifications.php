<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $needsAssignmentMode = !Schema::hasColumn('import_batches', 'assignment_mode');
        $needsAssignmentSummary = !Schema::hasColumn('import_batches', 'assignment_summary');

        if ($needsAssignmentMode || $needsAssignmentSummary) {
            Schema::table('import_batches', function (Blueprint $table) use ($needsAssignmentMode, $needsAssignmentSummary) {
                if ($needsAssignmentMode) {
                    $table->string('assignment_mode', 30)->default('file')->after('filename');
                }
                if ($needsAssignmentSummary) {
                    $table->json('assignment_summary')->nullable()->after('errors');
                }
            });
        }

        if (!Schema::hasColumn('prospects', 'import_batch_id')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->foreignId('import_batch_id')->nullable()->after('created_by')
                    ->constrained('import_batches')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prospects', 'import_batch_id')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('import_batch_id');
            });
        }

        $hasAssignmentMode = Schema::hasColumn('import_batches', 'assignment_mode');
        $hasAssignmentSummary = Schema::hasColumn('import_batches', 'assignment_summary');
        if ($hasAssignmentMode || $hasAssignmentSummary) {
            Schema::table('import_batches', function (Blueprint $table) use ($hasAssignmentMode, $hasAssignmentSummary) {
                $columns = [];
                if ($hasAssignmentMode) $columns[] = 'assignment_mode';
                if ($hasAssignmentSummary) $columns[] = 'assignment_summary';
                if ($columns) $table->dropColumn($columns);
            });
        }

        // Notifications may be reused by future CRM modules, so the table is intentionally retained.
    }
};
