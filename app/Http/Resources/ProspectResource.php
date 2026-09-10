<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'website' => $this->website,
            'city' => $this->city,
            'service' => $this->service,
            'route' => $this->route,
            'company_size' => $this->company_size,
            'contact_name' => $this->contact_name,
            'contact_position' => $this->contact_position,
            'phone' => $this->phone,
            'email' => $this->email,
            'current_system' => $this->current_system,
            'tracking_portal' => $this->tracking_portal,
            'pain_hypothesis' => $this->pain_hypothesis,
            'fit_score' => $this->fit_score,
            'pain_score' => $this->pain_score,
            'contact_score' => $this->contact_score,
            'total_score' => $this->total_score,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'last_contact_at' => $this->last_contact_at?->toIso8601String(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'last_outbound_at' => $this->last_outbound_at?->toIso8601String(),
            'last_customer_reply_at' => $this->last_customer_reply_at?->toIso8601String(),
            'follow_up_snoozed_until' => $this->follow_up_snoozed_until?->toIso8601String(),
            'follow_up_count' => (int) ($this->follow_up_count ?? 0),
            'last_follow_up_message' => $this->last_follow_up_message,
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'notes' => $this->notes,
            'estimated_deal_value' => (float) $this->estimated_deal_value,
            'actual_deal_value' => $this->actual_deal_value !== null ? (float) $this->actual_deal_value : null,
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id' => $this->assignedUser->id, 'name' => $this->assignedUser->name, 'email' => $this->assignedUser->email,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? ['id'=>$this->creator->id,'name'=>$this->creator->name] : null),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($activity) => [
                'id'=>$activity->id,'type'=>$activity->type,'type_label'=>$activity->type_label ?? ($activity::TYPES[$activity->type] ?? $activity->type),
                'title'=>$activity->title,'description'=>$activity->description,
                'occurred_at'=>$activity->occurred_at?->toIso8601String(),
                'user'=>$activity->user ? ['id'=>$activity->user->id,'name'=>$activity->user->name] : null,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
