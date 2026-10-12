<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Incoming WhatsApp contact, retained even when a CRM prospect does not exist. */
class WhatsAppInboundContact extends Model
{
    protected $table = 'whatsapp_inbound_contacts';

    protected $fillable = [
        'organization_id', 'prospect_id', 'phone_normalized', 'wa_id',
        'profile_name', 'source_name', 'source_id', 'source_url',
        'referral', 'first_message_at', 'last_message_at', 'last_message_id',
    ];

    protected function casts(): array
    {
        return [
            'referral' => 'array',
            'first_message_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function prospect(): BelongsTo { return $this->belongsTo(Prospect::class); }
    public function review(): HasOne { return $this->hasOne(WhatsAppLeadReview::class, 'whatsapp_inbound_contact_id'); }
}
