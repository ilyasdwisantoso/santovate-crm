<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    protected $fillable = [
        'organization_id', 'email', 'role', 'token_hash', 'status',
        'invited_by', 'accepted_user_id', 'expires_at', 'accepted_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isRedeemable(): bool
    {
        return $this->status === 'pending' && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
