<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessConfiguration extends Model
{
    protected $fillable = [
        'key','name','industry','description','theme','monthly_addon_price','annual_addon_price',
        'pipeline','terminology','follow_up_rules','demo_templates','demo_products','is_active','sort_order',
    ];

    protected function casts(): array
    {
        return [
            'theme'=>'array','pipeline'=>'array','terminology'=>'array','follow_up_rules'=>'array',
            'demo_templates'=>'array','demo_products'=>'array','is_active'=>'boolean',
            'monthly_addon_price'=>'integer','annual_addon_price'=>'integer',
        ];
    }
}
