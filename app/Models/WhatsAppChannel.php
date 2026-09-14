<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WhatsAppChannel extends Model {
    protected $table='whatsapp_channels';
    protected $fillable=['organization_id','provider','phone_number_id','waba_id','access_token','is_active','verified_at'];
    protected $hidden=['access_token'];
    protected function casts(): array { return ['access_token'=>'encrypted','is_active'=>'boolean','verified_at'=>'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function ready(): bool { return $this->is_active && filled($this->phone_number_id) && filled($this->access_token); }
}
