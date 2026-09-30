<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class QuotationRevision extends Model {
    protected $fillable=['quotation_id','revision_number','created_by','reason','snapshot'];
    protected function casts():array{return['revision_number'=>'integer','snapshot'=>'array'];}
    public function quotation():BelongsTo{return $this->belongsTo(Quotation::class);}
    public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}
