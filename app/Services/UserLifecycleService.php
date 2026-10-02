<?php

namespace App\Services;

use App\Models\ProspectAssignmentHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserLifecycleService
{
    /**
     * References that make a hard delete unsafe because they are part of CRM/audit history.
     * Missing tables/columns are ignored so this remains compatible across incremental deployments.
     */
    private const REFERENCES = [
        ['prospects', 'assigned_to', 'Prospek assigned'],
        ['prospects', 'created_by', 'Prospek dibuat'],
        ['prospect_activities', 'user_id', 'Aktivitas prospek'],
        ['prospect_assignment_histories', 'from_user_id', 'Riwayat assignment (asal)'],
        ['prospect_assignment_histories', 'to_user_id', 'Riwayat assignment (tujuan)'],
        ['prospect_assignment_histories', 'changed_by', 'Riwayat assignment (pengubah)'],
        ['opportunities', 'owner_id', 'Opportunity dimiliki'],
        ['opportunities', 'created_by', 'Opportunity dibuat'],
        ['quotations', 'created_by', 'Quotation dibuat'],
        ['quotations', 'approved_by', 'Quotation disetujui'],
        ['quotation_revisions', 'created_by', 'Revisi quotation'],
        ['deals', 'owner_id', 'Deal dimiliki'],
        ['deals', 'created_by', 'Deal dibuat'],
        ['deal_documents', 'uploaded_by', 'Dokumen deal'],
        ['commercial_invoices', 'issued_by', 'Invoice diterbitkan'],
        ['commercial_invoices', 'voided_by', 'Invoice dibatalkan'],
        ['payment_transactions', 'uploaded_by', 'Payment diunggah'],
        ['payment_transactions', 'verified_by', 'Payment diverifikasi'],
        ['payment_transactions', 'rejected_by', 'Payment ditolak'],
        ['payment_refunds', 'requested_by', 'Refund diminta'],
        ['payment_refunds', 'processed_by', 'Refund diproses'],
        ['sales_commissions', 'user_id', 'Komisi sales'],
        ['sales_commissions', 'approved_by', 'Komisi disetujui'],
        ['commission_ledger_entries', 'created_by', 'Ledger komisi'],
        ['sales_targets', 'user_id', 'Target sales'],
        ['approval_requests', 'requested_by', 'Approval diminta'],
        ['approval_requests', 'assigned_to', 'Approval ditugaskan'],
        ['approval_requests', 'decided_by', 'Approval diputuskan'],
        ['platform_audit_logs', 'actor_id', 'Platform audit'],
        ['entitlement_grants', 'created_by', 'Entitlement grant'],
        ['import_batches', 'created_by', 'Import batch'],
        ['follow_up_templates', 'created_by', 'Template follow-up'],
        ['campaigns', 'created_by', 'Campaign'],
        ['subscription_addon_orders', 'created_by', 'Order add-on'],
    ];

    public function summaries(Collection $users, User $actor): Collection
    {
        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->values();
        if ($ids->isEmpty()) return collect();

        $referenceMaps = [];
        foreach ($this->availableReferences() as [$table, $column, $label]) {
            $referenceMaps[] = [
                'table' => $table,
                'column' => $column,
                'label' => $label,
                'counts' => DB::table($table)
                    ->select($column, DB::raw('COUNT(*) as aggregate'))
                    ->whereIn($column, $ids->all())
                    ->groupBy($column)
                    ->pluck('aggregate', $column)
                    ->map(fn ($count) => (int) $count),
            ];
        }

        $openOpportunityCounts = collect();
        if (Schema::hasTable('opportunities') && Schema::hasColumn('opportunities', 'owner_id')) {
            $openOpportunityCounts = DB::table('opportunities')
                ->select('owner_id', DB::raw('COUNT(*) as aggregate'))
                ->whereIn('owner_id', $ids->all())
                ->when(Schema::hasColumn('opportunities', 'status'), fn ($q) => $q->where('status', 'open'))
                ->groupBy('owner_id')
                ->pluck('aggregate', 'owner_id')
                ->map(fn ($count) => (int) $count);
        }

        $activeAdminIds = User::query()
            ->where('organization_id', $actor->organization_id)
            ->where('role', 'admin')
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $users->mapWithKeys(function (User $user) use ($actor, $referenceMaps, $openOpportunityCounts, $activeAdminIds) {
            $references = [];
            foreach ($referenceMaps as $reference) {
                $count = (int) ($reference['counts']->get($user->id, 0));
                if ($count > 0) {
                    $references[] = [
                        'table' => $reference['table'],
                        'column' => $reference['column'],
                        'label' => $reference['label'],
                        'count' => $count,
                    ];
                }
            }

            $assignedProspects = collect($references)->first(
                fn ($item) => $item['table'] === 'prospects' && $item['column'] === 'assigned_to'
            )['count'] ?? 0;
            $openOpportunities = (int) $openOpportunityCounts->get($user->id, 0);
            $workloadTotal = $assignedProspects + $openOpportunities;
            $historyTotal = array_sum(array_column($references, 'count'));

            $isSelf = (int) $actor->id === (int) $user->id;
            $isPlatformAdmin = $user->isPlatformAdmin();
            $isLastAdmin = $user->role === 'admin' && $user->is_active
                && $activeAdminIds->filter(fn ($id) => $id !== (int) $user->id)->isEmpty();
            $canDeactivate = !$isSelf && !$isPlatformAdmin && !$isLastAdmin;
            $canDelete = $canDeactivate && !$user->is_active && $historyTotal === 0;

            $blockers = [];
            if ($isSelf) $blockers[] = 'Akun yang sedang digunakan tidak dapat dihapus atau dinonaktifkan.';
            if ($isPlatformAdmin) $blockers[] = 'Platform Owner / Platform Admin tidak dapat dihapus dari tenant.';
            if ($isLastAdmin) $blockers[] = 'Administrator aktif terakhir harus tetap tersedia.';
            if ($user->is_active) $blockers[] = 'Nonaktifkan akun terlebih dahulu sebelum hapus permanen.';
            foreach ($references as $reference) {
                $blockers[] = $reference['label'].' ('.$reference['count'].')';
            }

            return [$user->id => [
                'is_self' => $isSelf,
                'is_platform_admin' => $isPlatformAdmin,
                'is_last_admin' => $isLastAdmin,
                'can_deactivate' => $canDeactivate,
                'can_delete' => $canDelete,
                'requires_reassignment' => $workloadTotal > 0,
                'workload' => [
                    'assigned_prospects' => $assignedProspects,
                    'open_opportunities' => $openOpportunities,
                    'total' => $workloadTotal,
                ],
                'history_total' => $historyTotal,
                'history' => $references,
                'blockers' => $blockers,
            ]];
        });
    }

    public function summary(User $user, User $actor): array
    {
        return $this->summaries(collect([$user]), $actor)->get($user->id, []);
    }

    public function deactivateAndReassign(User $actor, User $user, ?int $replacementUserId = null): void
    {
        $this->assertSameOrganization($actor, $user);
        $summary = $this->summary($user, $actor);
        if (!$summary['can_deactivate']) {
            throw ValidationException::withMessages(['user' => $summary['blockers'][0] ?? 'Akun tidak dapat dinonaktifkan.']);
        }

        $replacement = null;
        if ($summary['requires_reassignment']) {
            if (!$replacementUserId) {
                throw ValidationException::withMessages(['replacement_user_id' => 'Pilih Account Executive pengganti untuk memindahkan workload aktif.']);
            }
            $replacement = User::query()
                ->where('organization_id', $user->organization_id)
                ->whereKey($replacementUserId)
                ->where('id', '!=', $user->id)
                ->where('role', 'sales')
                ->where('is_active', true)
                ->first();
            if (!$replacement) {
                throw ValidationException::withMessages(['replacement_user_id' => 'Account Executive pengganti tidak valid atau tidak aktif.']);
            }
        }

        DB::transaction(function () use ($actor, $user, $replacement) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($replacement) {
                $prospectIds = DB::table('prospects')
                    ->where('organization_id', $lockedUser->organization_id)
                    ->where('assigned_to', $lockedUser->id)
                    ->pluck('id');

                foreach ($prospectIds->chunk(400) as $chunk) {
                    DB::table('prospects')->whereIn('id', $chunk->all())->update([
                        'assigned_to' => $replacement->id,
                        'updated_at' => now(),
                    ]);

                    $rows = $chunk->map(fn ($prospectId) => [
                        'organization_id' => $lockedUser->organization_id,
                        'prospect_id' => $prospectId,
                        'from_user_id' => $lockedUser->id,
                        'to_user_id' => $replacement->id,
                        'changed_by' => $actor->id,
                        'reason' => 'Workload dipindahkan saat akun dinonaktifkan.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all();
                    if ($rows) ProspectAssignmentHistory::query()->insert($rows);
                }

                if (Schema::hasTable('opportunities') && Schema::hasColumn('opportunities', 'owner_id')) {
                    DB::table('opportunities')
                        ->where('organization_id', $lockedUser->organization_id)
                        ->where('owner_id', $lockedUser->id)
                        ->where('status', 'open')
                        ->update(['owner_id' => $replacement->id, 'updated_at' => now()]);
                }
            }

            $lockedUser->update(['is_active' => false]);
        });
    }

    public function assertCanDelete(User $actor, User $user): void
    {
        $this->assertSameOrganization($actor, $user);
        $summary = $this->summary($user, $actor);
        if (!$summary['can_delete']) {
            throw ValidationException::withMessages([
                'user' => $summary['blockers'][0] ?? 'Akun memiliki histori CRM dan tidak dapat dihapus permanen.',
            ]);
        }
    }

    public function cleanupSessionRows(User $user): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    private function availableReferences(): array
    {
        static $available = null;
        if ($available !== null) return $available;

        $available = [];
        foreach (self::REFERENCES as $reference) {
            [$table, $column] = $reference;
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) $available[] = $reference;
        }

        // Production/local Santovate use MySQL/MariaDB. Discover every FK that points to users.id
        // so a future table cannot silently cascade/delete history just because it was not added above.
        if (DB::connection()->getDriverName() === 'mysql') {
            $database = DB::connection()->getDatabaseName();
            $known = collect($available)->map(fn ($ref) => $ref[0].'.'.$ref[1])->all();
            $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->select(['TABLE_NAME', 'COLUMN_NAME'])
                ->where('TABLE_SCHEMA', $database)
                ->where('REFERENCED_TABLE_SCHEMA', $database)
                ->where('REFERENCED_TABLE_NAME', 'users')
                ->where('REFERENCED_COLUMN_NAME', 'id')
                ->get();

            foreach ($foreignKeys as $foreignKey) {
                $key = $foreignKey->TABLE_NAME.'.'.$foreignKey->COLUMN_NAME;
                if (!in_array($key, $known, true)) {
                    $available[] = [$foreignKey->TABLE_NAME, $foreignKey->COLUMN_NAME, 'Referensi '.$key];
                    $known[] = $key;
                }
            }
        }

        return $available;
    }

    private function hasAnotherActiveAdmin(User $user): bool
    {
        return User::query()
            ->where('organization_id', $user->organization_id)
            ->where('role', 'admin')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();
    }

    private function assertSameOrganization(User $actor, User $user): void
    {
        abort_unless((int) $actor->organization_id === (int) $user->organization_id, 403);
    }
}
