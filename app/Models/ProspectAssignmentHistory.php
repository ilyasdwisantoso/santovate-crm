<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProspectAssignmentHistory extends Model {
    protected $fillable=['organization_id','prospect_id','from_user_id','to_user_id','changed_by','reason'];
    public function fromUser():BelongsTo{return $this->belongsTo(User::class,'from_user_id');}
    public function toUser():BelongsTo{return $this->belongsTo(User::class,'to_user_id');}
    public function changer():BelongsTo{return $this->belongsTo(User::class,'changed_by');}
}
