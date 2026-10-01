<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\PlatformAuditLog;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\ApprovalDecidedNotification;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function syncQuotation(Quotation $quotation): ?ApprovalRequest
    {
        $quotation->loadMissing(['organization','creator','prospect','items']);

        $needsApproval = $quotation->requires_approval
            && $quotation->approval_status === 'pending'
            && $quotation->status === 'pending_approval';

        $existing = ApprovalRequest::query()
            ->where('type','quotation_commercial')
            ->where('subject_type',Quotation::class)
            ->where('subject_id',$quotation->id)
            ->where('status','pending')
            ->first();

        if (!$needsApproval) {
            if ($existing) {
                $existing->update([
                    'status'=>'cancelled',
                    'decision_notes'=>'Quotation direvisi atau tidak lagi membutuhkan approval.',
                    'decided_at'=>now(),
                ]);
            }
            return null;
        }

        $reasons = $this->quotationReasons($quotation);
        $scope = $this->isInternalOrganization($quotation->organization) ? 'platform' : 'tenant';
        $title = 'Approval quotation '.$quotation->quotation_number;
        $summary = trim(($quotation->prospect?->company_name ?: 'Client').' · '.implode(' · ', array_column($reasons,'label')));
        $payload = [
            'organization_id'=>$quotation->organization_id,
            'requested_by'=>$quotation->created_by,
            'approval_scope'=>$scope,
            'type'=>'quotation_commercial',
            'subject_type'=>Quotation::class,
            'subject_id'=>$quotation->id,
            'status'=>'pending',
            'title'=>$title,
            'summary'=>$summary,
            'amount'=>$quotation->grand_total,
            'metadata'=>[
                'quotation_number'=>$quotation->quotation_number,
                'company_name'=>$quotation->prospect?->company_name,
                'revision_number'=>$quotation->revision_number,
                'reasons'=>$reasons,
                'grand_total'=>(float)$quotation->grand_total,
                'discount_amount'=>(float)$quotation->discount_amount,
                'payment_terms'=>$quotation->payment_terms,
            ],
            'requested_at'=>$existing?->requested_at ?: now(),
        ];

        if ($existing) {
            $existing->update($payload);
            return $existing->fresh(['organization','requester']);
        }

        $approval = ApprovalRequest::create($payload);
        $this->notifyApprovers($approval);
        return $approval->fresh(['organization','requester']);
    }

    public function createManual(User $requester, array $data): ApprovalRequest
    {
        $scope = $this->isInternalOrganization($requester->organization) ? 'platform' : 'tenant';

        $approval = ApprovalRequest::create([
            'organization_id'=>$requester->organization_id,
            'requested_by'=>$requester->id,
            'approval_scope'=>$scope,
            'type'=>$data['type'],
            'subject_type'=>$data['subject_type'] ?? null,
            'subject_id'=>$data['subject_id'] ?? null,
            'status'=>'pending',
            'title'=>$data['title'],
            'summary'=>$data['summary'] ?? null,
            'amount'=>$data['amount'] ?? null,
            'metadata'=>$data['metadata'] ?? null,
            'requested_at'=>now(),
        ]);

        $this->notifyApprovers($approval);
        return $approval;
    }

    public function decide(ApprovalRequest $approval, User $actor, string $status, ?string $notes = null): ApprovalRequest
    {
        if (!in_array($status,['approved','approved_with_conditions','rejected'],true)) {
            throw ValidationException::withMessages(['status'=>'Keputusan approval tidak valid.']);
        }

        return DB::transaction(function () use ($approval,$actor,$status,$notes) {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['approval'=>'Approval ini sudah diputuskan.']);
            }

            $this->authorizeDecision($locked,$actor);

            $locked->update([
                'status'=>$status,
                'decision_notes'=>$notes,
                'decided_by'=>$actor->id,
                'decided_at'=>now(),
            ]);

            if ($locked->subject_type === Quotation::class && $locked->subject_id) {
                $quotation = Quotation::query()->lockForUpdate()->find($locked->subject_id);
                if ($quotation) {
                    if (in_array($status,['approved','approved_with_conditions'],true)) {
                        $quotation->forceFill([
                            'status'=>'approved',
                            'approval_status'=>'approved',
                            'approved_by'=>$actor->id,
                            'approved_at'=>now(),
                        ])->saveQuietly();
                    } else {
                        // Owner rejection means "revise commercial terms", not client rejection.
                        $quotation->forceFill([
                            'status'=>'draft',
                            'approval_status'=>'rejected',
                            'approved_by'=>null,
                            'approved_at'=>null,
                        ])->saveQuietly();
                    }
                }
            }

            if ($locked->approval_scope === 'platform') {
                PlatformAuditLog::create([
                    'actor_id'=>$actor->id,
                    'organization_id'=>$locked->organization_id,
                    'action'=>'approval.'.$status,
                    'target_type'=>ApprovalRequest::class,
                    'target_id'=>$locked->id,
                    'metadata'=>['type'=>$locked->type,'notes'=>$notes],
                ]);
            }

            $locked->requester?->notify(new ApprovalDecidedNotification($locked->fresh()));
            return $locked->fresh(['organization','requester','decider']);
        });
    }

    private function authorizeDecision(ApprovalRequest $approval, User $actor): void
    {
        if ($approval->approval_scope === 'platform') {
            abort_unless($actor->is_active && $actor->isPlatformAdmin(),403);
            return;
        }

        abort_unless(
            $actor->is_active && $actor->isAdmin() && $actor->organization_id === $approval->organization_id,
            403
        );
    }

    private function notifyApprovers(ApprovalRequest $approval): void
    {
        $query = User::query()->where('is_active',true);
        if ($approval->approval_scope === 'platform') {
            $query->where('is_platform_admin',true);
        } else {
            $query->where('organization_id',$approval->organization_id)->where('role','admin');
        }

        $query->get()->each(fn (User $user) => $user->notify(new ApprovalRequestedNotification($approval)));
    }

    private function quotationReasons(Quotation $quotation): array
    {
        $reasons = [];
        $subtotal = (float)$quotation->subtotal;
        $discountPercent = $subtotal > 0 ? ((float)$quotation->discount_amount / $subtotal * 100) : 0;

        $discountThreshold = (float)config('approvals.quotation.discount_percent_threshold',5);
        if ($discountPercent > $discountThreshold) {
            $reasons[] = ['code'=>'discount','label'=>'Discount '.round($discountPercent,1).'%'];
        }
        if ($quotation->pricing_type === 'custom') {
            $reasons[] = ['code'=>'custom_scope','label'=>'Custom pricing / scope'];
        }
        $highValueThreshold = (float)config('approvals.quotation.high_value_threshold',50000000);
        if ((float)$quotation->grand_total >= $highValueThreshold) {
            $reasons[] = ['code'=>'high_value','label'=>'High value ≥ Rp'.number_format($highValueThreshold,0,',','.')];
        }

        $belowFloor = $quotation->items->contains(fn ($item) => $item->minimum_price_snapshot !== null
            && (float)$item->minimum_price_snapshot > 0
            && (float)$item->unit_price + 0.01 < (float)$item->minimum_price_snapshot);
        if ($belowFloor) $reasons[] = ['code'=>'below_floor','label'=>'Harga di bawah minimum'];

        $belowCost = $quotation->items->contains(fn ($item) => $item->internal_cost_snapshot !== null
            && (float)$item->internal_cost_snapshot > 0
            && (float)$item->unit_price + 0.01 < (float)$item->internal_cost_snapshot);
        if ($belowCost) $reasons[] = ['code'=>'below_cost','label'=>'Harga di bawah cost'];

        $defaultTerms = $this->normalizeTerms((string)config('approvals.quotation.default_payment_terms','50% DP, 30% UAT, 20% Go-Live'));
        if (filled($quotation->payment_terms) && $this->normalizeTerms($quotation->payment_terms) !== $defaultTerms) {
            $reasons[] = ['code'=>'payment_terms','label'=>'Payment terms khusus'];
        }

        if (!$reasons) $reasons[] = ['code'=>'commercial_review','label'=>'Commercial review'];
        return $reasons;
    }

    private function normalizeTerms(?string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', (string)$value));
    }

    private function isInternalOrganization(?Model $organization): bool
    {
        if (!$organization) return false;
        return $organization->slug === 'santovate-internal' || (bool)data_get($organization->settings,'internal',false);
    }
}
