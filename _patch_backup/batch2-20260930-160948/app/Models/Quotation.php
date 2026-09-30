<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    public const STATUSES = [
        'draft'=>'Draft','pending_approval'=>'Pending Approval','approved'=>'Approved','sent'=>'Sent','viewed'=>'Viewed',
        'accepted'=>'Accepted','rejected'=>'Rejected','expired'=>'Expired','revised'=>'Revised','cancelled'=>'Cancelled',
    ];
    public const TYPES = ['project'=>'Project','subscription'=>'Subscription','both'=>'Project + Subscription'];

    protected $fillable = [
        'organization_id','prospect_id','opportunity_id','created_by','approved_by','quotation_number','revision_number',
        'quotation_type','status','pricing_type','currency','subtotal','discount_type','discount_value','discount_amount',
        'tax_percent','tax_amount','grand_total','payment_terms','project_timeline','valid_until','notes','requires_approval',
        'approval_status','approved_at','sent_at','viewed_at','accepted_at','rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number'=>'integer','subtotal'=>'decimal:2','discount_value'=>'decimal:2','discount_amount'=>'decimal:2',
            'tax_percent'=>'decimal:2','tax_amount'=>'decimal:2','grand_total'=>'decimal:2','requires_approval'=>'boolean',
            'valid_until'=>'date','approved_at'=>'datetime','sent_at'=>'datetime','viewed_at'=>'datetime','accepted_at'=>'datetime','rejected_at'=>'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function prospect(): BelongsTo { return $this->belongsTo(Prospect::class); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function items(): HasMany { return $this->hasMany(QuotationItem::class)->orderBy('sort_order'); }
    public function revisions(): HasMany { return $this->hasMany(QuotationRevision::class)->orderByDesc('revision_number'); }
    public function deal() { return $this->hasOne(Deal::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('organization_id', $user->organization_id);
        if ($user->isAdmin()) return $query;
        return $query->whereHas('prospect', fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? ucfirst($this->status); }
}
