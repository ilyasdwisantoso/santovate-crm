<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends Model
{
    use HasFactory;

    public const TYPES = [
        'note'=>'Catatan','call'=>'Telepon','whatsapp'=>'WhatsApp','email'=>'Email','meeting'=>'Meeting',
        'demo'=>'Demo','proposal'=>'Proposal','status_change'=>'Perubahan Status','follow_up'=>'Follow-up',
        'customer_reply'=>'Balasan Customer',
    ];

    protected $fillable=['prospect_id','user_id','type','title','description','occurred_at'];
    protected function casts(): array { return ['occurred_at'=>'datetime']; }
    public function prospect(): BelongsTo { return $this->belongsTo(Prospect::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? ucfirst($this->type); }
}
