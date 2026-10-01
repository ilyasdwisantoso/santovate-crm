<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlatformAuditLog extends Model
{
    protected $fillable = ['actor_id','organization_id','action','target_type','target_id','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function actor(): BelongsTo { return $this->belongsTo(User::class,'actor_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function target(): MorphTo { return $this->morphTo(); }
}
