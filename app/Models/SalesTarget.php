<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesTarget extends Model {
    use HasFactory;
    protected $fillable=['organization_id','user_id','year','month','target_contacted','target_meetings','target_proposals','target_deals','target_revenue'];
    protected function casts(): array { return ['year'=>'integer','month'=>'integer','target_contacted'=>'integer','target_meetings'=>'integer','target_proposals'=>'integer','target_deals'=>'integer','target_revenue'=>'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
