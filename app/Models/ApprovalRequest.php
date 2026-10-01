<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    public const STATUSES = [
        'pending'=>'Pending',
        'approved'=>'Approved',
        'approved_with_conditions'=>'Approved with Conditions',
        'rejected'=>'Rejected',
        'cancelled'=>'Cancelled',
    ];

    public const TYPES = [
        'quotation_commercial'=>'Quotation Commercial',
        'final_price'=>'Final Price',
        'payment_terms'=>'Payment Terms',
        'custom_scope'=>'Custom Scope',
        'meeting_expense'=>'Meeting / Travel Expense',
        'project_exception'=>'Project Exception',
        'refund'=>'Refund',
        'commission_adjustment'=>'Commission Adjustment',
    ];

    protected $fillable = [
        'organization_id','requested_by','assigned_to','approval_scope','type','subject_type','subject_id',
        'status','title','summary','amount','metadata','decision_notes','decided_by','requested_at','decided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'=>'decimal:2','metadata'=>'array','requested_at'=>'datetime','decided_at'=>'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
    public function subject(): MorphTo { return $this->morphTo(); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isPlatformAdmin()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('approval_scope','platform')
                    ->orWhere(function (Builder $tenant) use ($user) {
                        $tenant->where('organization_id',$user->organization_id)
                            ->where('approval_scope','tenant');
                    });
            });
        }

        $internal = $user->organization?->slug === 'santovate-internal'
            || (bool)data_get($user->organization?->settings,'internal',false);
        $scope = $internal ? 'platform' : 'tenant';
        $query->where('organization_id',$user->organization_id)->where('approval_scope',$scope);
        if ($user->isAdmin()) return $query;
        return $query->where('requested_by',$user->id);
    }

    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? ucfirst(str_replace('_',' ',$this->status)); }
    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? ucfirst(str_replace('_',' ',$this->type)); }
}
