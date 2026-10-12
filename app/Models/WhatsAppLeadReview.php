<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLeadReview extends Model
{
    // The exact table created by the V9.5.1 migration. Eloquent would otherwise
    // infer "whats_app_lead_reviews" from the acronym in this class name.
    protected $table = 'whatsapp_lead_reviews';

    protected $fillable = [
        'organization_id', 'whatsapp_inbound_contact_id', 'assigned_to',
        'reviewed_by', 'status', 'lead_kind', 'notes', 'reviewed_at',
    ];

    protected function casts(): array { return ['reviewed_at'=>'datetime']; }
    public function contact(): BelongsTo { return $this->belongsTo(WhatsAppInboundContact::class, 'whatsapp_inbound_contact_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
