<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    public const STAGES = [
        'qualification'=>'Qualification','discovery'=>'Discovery','solution'=>'Solution Fit',
        'proposal'=>'Proposal','negotiation'=>'Negotiation','won'=>'Won','lost'=>'Lost',
    ];
    public const STATUSES = ['open'=>'Open','won'=>'Won','lost'=>'Lost'];
    public const URGENCIES = ['low'=>'Low','medium'=>'Medium','high'=>'High'];
    public const LOST_REASONS = ['price'=>'Price','competitor'=>'Competitor','no_budget'=>'No Budget','no_response'=>'No Response','postponed'=>'Project Postponed','internal_development'=>'Internal Development','not_fit'=>'Requirement Not Fit','other'=>'Other'];

    protected $fillable = [
        'organization_id','prospect_id','owner_id','created_by','name','status','stage','business_problem','current_process',
        'required_solution','required_features','estimated_users','budget','expected_value','probability','target_go_live',
        'decision_maker','decision_process','urgency','next_action','next_follow_up_at','lost_reason','competitor','recontact_at','closed_at',
    ];

    protected function casts(): array
    {
        return [
            'budget'=>'decimal:2','expected_value'=>'decimal:2','probability'=>'integer','estimated_users'=>'integer',
            'target_go_live'=>'date','next_follow_up_at'=>'datetime','recontact_at'=>'datetime','closed_at'=>'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function prospect(): BelongsTo { return $this->belongsTo(Prospect::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function quotations(): HasMany { return $this->hasMany(Quotation::class); }
    public function deals(): HasMany { return $this->hasMany(Deal::class); }
    public function solutionItems(): BelongsToMany
    {
        return $this->belongsToMany(SolutionCatalogItem::class, 'opportunity_solution_item')
            ->withPivot(['quantity','notes'])->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('organization_id', $user->organization_id);
        if ($user->isAdmin()) return $query;
        return $query->where(fn (Builder $q) => $q->where('owner_id', $user->id)->orWhere('created_by', $user->id));
    }

    public function getStageLabelAttribute(): string { return self::STAGES[$this->stage] ?? ucfirst($this->stage); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? ucfirst($this->status); }
}
