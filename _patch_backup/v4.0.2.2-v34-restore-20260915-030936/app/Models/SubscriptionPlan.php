<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model {
    protected $fillable=['key','name','description','monthly_price','annual_price','user_limit','prospect_limit','features','is_active','sort_order'];
    protected function casts(): array { return ['features'=>'array','is_active'=>'boolean','monthly_price'=>'integer','annual_price'=>'integer','user_limit'=>'integer','prospect_limit'=>'integer']; }
}
