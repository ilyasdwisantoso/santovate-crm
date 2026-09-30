<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DealPaymentSchedule extends Model {
    protected $fillable=['deal_id','sequence','label','percentage','amount','trigger_type','due_date','status','notes'];
    protected function casts():array{return['percentage'=>'decimal:2','amount'=>'decimal:2','due_date'=>'date','sequence'=>'integer'];}
    public function deal():BelongsTo{return $this->belongsTo(Deal::class);}
    public function invoices():HasMany{return $this->hasMany(CommercialInvoice::class);}
}
