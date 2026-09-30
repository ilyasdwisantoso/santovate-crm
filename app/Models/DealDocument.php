<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DealDocument extends Model {
    protected $fillable=['deal_id','uploaded_by','type','reference_number','original_name','file_path'];
    public function deal():BelongsTo{return $this->belongsTo(Deal::class);}
    public function uploader():BelongsTo{return $this->belongsTo(User::class,'uploaded_by');}
}
