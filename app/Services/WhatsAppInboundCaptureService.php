<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppInboundContact;
use App\Models\WhatsAppMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Signed, tenant-routed webhook processing. Unknown numbers stay in the inbound
 * contacts queue without being misrepresented as companies in the B2B CRM.
 * Individual identities are connected only after explicit lead review.
 */
class WhatsAppInboundCaptureService
{
    public function capture(WhatsAppChannel $channel, array $message, array $value, ProspectStageService $stages): void
    {
        $orgId = (int) $channel->organization_id;
        $id = trim((string) ($message['id'] ?? ''));
        $waId = trim((string) ($message['from'] ?? ''));
        $from = Prospect::normalizePhone($waId);
        if ($id === '' || strlen($id) > 255 || !$from || strlen($from) > 32) return;

        // Meta can retry deliveries. The provider message ID is globally unique
        // in the existing schema; a replay must not create another activity.
        if (WhatsAppMessage::query()->where('provider_message_id', $id)->exists()) return;

        $profileName = null;
        foreach ((array) ($value['contacts'] ?? []) as $contact) {
            if (($contact['wa_id'] ?? null) === $waId) {
                $name = trim((string) data_get($contact, 'profile.name', ''));
                $profileName = $name !== '' ? mb_substr($name, 0, 255) : null;
                break;
            }
        }
        $referral = isset($message['referral']) && is_array($message['referral']) ? $message['referral'] : null;
        $isMetaAd = data_get($referral, 'source_type') === 'ad';
        $sourceUrl = (string) data_get($referral, 'source_url', '');
        $sourceUrl = filter_var($sourceUrl, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($sourceUrl, PHP_URL_SCHEME)), ['https','http'], true)
            ? mb_substr($sourceUrl, 0, 2000) : null;
        $sourceId = data_get($referral, 'source_id');
        $sourceId = is_scalar($sourceId) ? mb_substr((string) $sourceId, 0, 191) : null;
        $type = mb_substr((string) ($message['type'] ?? 'text'), 0, 30);
        $body = data_get($message, 'text.body')
            ?? data_get($message, 'button.text')
            ?? data_get($message, 'interactive.button_reply.title')
            ?? data_get($message, 'interactive.list_reply.title')
            ?? ('[Pesan WhatsApp: '.$type.']');
        $body = is_scalar($body) ? (string) $body : '[Pesan WhatsApp]';
        $timestamp = $message['timestamp'] ?? null;
        $when = is_scalar($timestamp) && ctype_digit((string) $timestamp)
            && strlen((string) $timestamp) <= 10
            ? Carbon::createFromTimestamp((int) $timestamp) : now();

        try {
            DB::transaction(function () use (
                $orgId, $id, $waId, $from, $profileName, $referral,
                $isMetaAd, $sourceId, $sourceUrl, $type, $body, $when, $message, $stages
            ): void {
                if (WhatsAppMessage::query()->where('provider_message_id', $id)->exists()) return;

                $contact = WhatsAppInboundContact::query()->firstOrCreate(
                    ['organization_id' => $orgId, 'phone_normalized' => $from],
                    [
                        'prospect_id' => null, 'wa_id' => $waId,
                        'profile_name' => $profileName,
                        'source_name' => $isMetaAd ? 'Meta Ads' : null,
                        'source_id' => $isMetaAd ? $sourceId : null,
                        'source_url' => $isMetaAd ? $sourceUrl : null,
                        'referral' => $referral,
                        'first_message_at' => $when,
                    ]
                );
                // Serialize against conversion/link before deriving or persisting associations.
                $contact = WhatsAppInboundContact::where('organization_id',$orgId)->lockForUpdate()->findOrFail($contact->id);
                $matches = Prospect::where('organization_id',$orgId)->where('phone_normalized',$from)->limit(2)->get();
                // Preserve unique B2B routing; new individual associations require manual review.
                $prospect = $matches->count() === 1 && $matches->first()->entity_type === 'business' ? $matches->first() : null;
                // An admin may have explicitly linked this sender to an existing
                // tenant-owned prospect. Do not erase that verified relationship
                // merely because another automatic phone match is absent.
                if ($contact->prospect_id) {
                    $manuallyLinked = Prospect::query()->where('organization_id', $orgId)->lockForUpdate()->find($contact->prospect_id);
                    if ($manuallyLinked) $prospect = $manuallyLinked;
                }
                $updates = [];
                if ($contact->prospect_id !== $prospect?->id) $updates['prospect_id'] = $prospect?->id;
                if ($profileName && $profileName !== $contact->profile_name) $updates['profile_name'] = $profileName;
                if ($waId && $waId !== $contact->wa_id) $updates['wa_id'] = $waId;
                if ($isMetaAd && !$contact->source_name) {
                    $updates['source_name'] = 'Meta Ads';
                    $updates['source_id'] = $sourceId;
                    $updates['source_url'] = $sourceUrl;
                    $updates['referral'] = $referral;
                }
                if (!$contact->last_message_at || $when->greaterThanOrEqualTo($contact->last_message_at)) {
                    $updates['last_message_at'] = $when;
                    $updates['last_message_id'] = $id;
                }
                if ($updates) $contact->update($updates);

                WhatsAppMessage::create([
                    'organization_id' => $orgId, 'prospect_id' => $prospect?->id,
                    'direction' => 'inbound', 'type' => $type, 'status' => 'received',
                    'from_phone' => $from, 'provider_message_id' => $id,
                    'body' => $body, 'provider_payload' => $message,
                ]);

                if (!$prospect) return; // Pending review or quota; message is retained.
                $now = now();
                $prospect->update([
                    'whatsapp_status' => 'valid', 'whatsapp_id' => $waId,
                    'whatsapp_verified_at' => $now, 'last_customer_reply_at' => $now,
                    'last_contact_at' => $now, 'replied_at' => $prospect->replied_at ?: $now,
                    'follow_up_snoozed_until' => null,
                ]);
                ProspectActivity::create([
                    'prospect_id' => $prospect->id, 'user_id' => null,
                    'type' => 'customer_reply', 'title' => 'Balasan WhatsApp diterima otomatis',
                    'description' => $body, 'occurred_at' => $now,
                ]);
                if (in_array($prospect->status, ['baru','diriset','dihubungi'], true)) {
                    $actor = $prospect->assignedUser ?: $prospect->creator
                        ?: $prospect->organization?->users()->where('role', 'admin')->first();
                    if ($actor) $stages->transition($prospect, 'membalas', $actor, 'WhatsApp webhook');
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            // A competing Meta retry may have committed the exact message first.
            if (WhatsAppMessage::query()->where('provider_message_id', $id)->exists()) return;
            throw $e;
        }
    }
}
