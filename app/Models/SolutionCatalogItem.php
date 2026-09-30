<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SolutionCatalogItem extends Model
{
    protected $fillable = [
        'organization_id','code','category','name','description','pricing_model','unit',
        'internal_cost','recommended_price','minimum_price','dependencies','upsell_notes',
        'sales_notes','is_active','sort_order',
    ];

    protected function casts(): array
    {
        return [
            'internal_cost'=>'decimal:2','recommended_price'=>'decimal:2','minimum_price'=>'decimal:2',
            'dependencies'=>'array','is_active'=>'boolean','sort_order'=>'integer',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_solution_item')
            ->withPivot(['quantity','notes'])
            ->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('organization_id', $user->organization_id);
    }
}
