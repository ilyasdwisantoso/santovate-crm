<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProspectStageService
{
    private const TIMESTAMP_BY_STATUS = [
        'dihubungi' => 'contacted_at',
        'membalas' => 'replied_at',
        'meeting' => 'meeting_at',
        'demo' => 'demo_at',
        'proposal' => 'proposal_at',
        'negosiasi' => 'negotiation_at',
        'deal' => 'deal_at',
    ];

    public function transition(Prospect $prospect, string $newStatus, User $actor, string $source = 'CRM'): Prospect
    {
        if ($prospect->status === $newStatus) {
            return $prospect;
        }

        return DB::transaction(function () use ($prospect, $newStatus, $actor, $source) {
            $oldStatus = $prospect->status;
            $updates = ['status' => $newStatus];

            // Any meaningful stage from 'contacted' onward implies the company has been contacted.
            if (in_array($newStatus, ['dihubungi','membalas','meeting','demo','proposal','negosiasi','deal'], true) && !$prospect->contacted_at) {
                $updates['contacted_at'] = now();
            }

            // Keep the follow-up work queue consistent even when an Account Executive
            // updates the pipeline directly instead of adding an activity first.
            if (in_array($newStatus, ['dihubungi','meeting','demo','proposal','negosiasi','deal'], true) && !$prospect->last_outbound_at) {
                $updates['last_outbound_at'] = now();
            }
            if ($newStatus === 'membalas' && !$prospect->last_customer_reply_at) {
                $updates['last_customer_reply_at'] = now();
            }

            if ($timestampColumn = self::TIMESTAMP_BY_STATUS[$newStatus] ?? null) {
                if (!$prospect->{$timestampColumn}) {
                    $updates[$timestampColumn] = now();
                }
            }

            if (in_array($newStatus, ['deal', 'ditolak', 'tidak_cocok'], true) && !$prospect->closed_at) {
                $updates['closed_at'] = now();
            }

            if (!in_array($newStatus, ['deal', 'ditolak', 'tidak_cocok'], true)) {
                $updates['closed_at'] = null;
            }

            $prospect->update($updates);

            ProspectActivity::create([
                'prospect_id' => $prospect->id,
                'user_id' => $actor->id,
                'type' => 'status_change',
                'title' => 'Status diperbarui',
                'description' => sprintf('%s → %s · %s', Prospect::STATUSES[$oldStatus] ?? $oldStatus, $prospect->status_label, $source),
                'occurred_at' => now(),
            ]);

            return $prospect->refresh();
        });
    }
}
