<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Deal extends Model {
    protected $fillable=['organization_id','prospect_id','opportunity_id','quotation_id','owner_id','created_by','deal_number','status','closing_date','quoted_value','actual_deal_value','commissionable_value','contract_status','project_start_date','special_agreement','notes'];
    protected function casts():array{return['closing_date'=>'date','project_start_date'=>'date','quoted_value'=>'decimal:2','actual_deal_value'=>'decimal:2','commissionable_value'=>'decimal:2'];}
    public function organization():BelongsTo{return $this->belongsTo(Organization::class);}
    public function prospect():BelongsTo{return $this->belongsTo(Prospect::class);}
    public function opportunity():BelongsTo{return $this->belongsTo(Opportunity::class);}
    public function quotation():BelongsTo{return $this->belongsTo(Quotation::class);}
    public function owner():BelongsTo{return $this->belongsTo(User::class,'owner_id');}
    public function documents():HasMany{return $this->hasMany(DealDocument::class);}
    public function paymentSchedules():HasMany{return $this->hasMany(DealPaymentSchedule::class)->orderBy('sequence');}
    public function invoices():HasMany{return $this->hasMany(CommercialInvoice::class);}
    public function commission():HasOne{return $this->hasOne(SalesCommission::class);}
    public function scopeVisibleTo(Builder $query,User $user):Builder{$query->where('organization_id',$user->organization_id);if($user->canManageFinance())return$query;return$query->where('owner_id',$user->id);}
}
