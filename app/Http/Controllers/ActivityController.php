<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Services\ProspectStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function store(Request $request, Prospect $prospect, ProspectStageService $stages): RedirectResponse
    {
        abort_unless(Prospect::query()->visibleTo($request->user())->whereKey($prospect->id)->exists(), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ProspectActivity::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys(Prospect::STATUSES))],
        ]);

        $occurredAt = $data['occurred_at'] ?? now();
        ProspectActivity::create([
            'prospect_id' => $prospect->id,
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'occurred_at' => $occurredAt,
        ]);

        $updates = [];
        if (!empty($data['next_follow_up_at'])) {
            $updates['next_follow_up_at'] = $data['next_follow_up_at'];
        }

        $outboundTypes = ['call', 'whatsapp', 'email', 'meeting', 'demo', 'proposal', 'follow_up'];
        if (in_array($data['type'], $outboundTypes, true)) {
            $updates['last_contact_at'] = $occurredAt;
            $updates['last_outbound_at'] = $occurredAt;
            $updates['follow_up_snoozed_until'] = null;
            if (!$prospect->contacted_at) {
                $updates['contacted_at'] = $occurredAt;
            }
        }

        if ($data['type'] === 'customer_reply') {
            $updates['last_contact_at'] = $occurredAt;
            $updates['last_customer_reply_at'] = $occurredAt;
            $updates['follow_up_snoozed_until'] = null;
            if (!$prospect->replied_at) {
                $updates['replied_at'] = $occurredAt;
            }
        }

        if ($data['type'] === 'meeting' && !$prospect->meeting_at) $updates['meeting_at'] = $occurredAt;
        if ($data['type'] === 'demo' && !$prospect->demo_at) $updates['demo_at'] = $occurredAt;
        if ($data['type'] === 'proposal' && !$prospect->proposal_at) $updates['proposal_at'] = $occurredAt;
        if ($updates) $prospect->update($updates);

        if ($data['type'] === 'customer_reply' && in_array($prospect->status, ['baru','diriset','dihubungi'], true)) {
            $stages->transition($prospect, 'membalas', $request->user(), 'Balasan customer');
        } elseif (!empty($data['status'])) {
            $stages->transition($prospect, $data['status'], $request->user(), 'Aktivitas');
        }

        return back()->with('success', 'Aktivitas berhasil dicatat.');
    }
}
