<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Services\FollowUpService;
use App\Services\ProspectStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request, FollowUpService $followUps): Response
    {
        return Inertia::render('FollowUps/Index', $followUps->payload($request->user()));
    }

    public function markCustomerReply(Request $request, Prospect $prospect, ProspectStageService $stages): RedirectResponse
    {
        $prospect = $this->visibleProspect($request, $prospect);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);
        $now = now();

        ProspectActivity::create([
            'prospect_id' => $prospect->id,
            'user_id' => $request->user()->id,
            'type' => 'customer_reply',
            'title' => 'Customer membalas',
            'description' => $data['note'] ?? null,
            'occurred_at' => $now,
        ]);

        $prospect->update([
            'last_customer_reply_at' => $now,
            'last_contact_at' => $now,
            'replied_at' => $prospect->replied_at ?: $now,
            'follow_up_snoozed_until' => null,
        ]);

        if (in_array($prospect->status, ['baru', 'diriset', 'dihubungi'], true)) {
            $stages->transition($prospect, 'membalas', $request->user(), 'Balasan customer');
        }

        return back()->with('success', 'Balasan customer dicatat. Lead masuk antrean Perlu Balas.');
    }

    public function markSent(Request $request, Prospect $prospect, ProspectStageService $stages): RedirectResponse
    {
        $prospect = $this->visibleProspect($request, $prospect);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'next_follow_up_days' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);
        $now = now();
        $days = array_key_exists('next_follow_up_days', $data) ? (int) $data['next_follow_up_days'] : 5;

        ProspectActivity::create([
            'prospect_id' => $prospect->id,
            'user_id' => $request->user()->id,
            'type' => 'whatsapp',
            'title' => 'WhatsApp follow-up dikirim',
            'description' => $data['message'],
            'occurred_at' => $now,
        ]);

        $prospect->update([
            'last_outbound_at' => $now,
            'last_contact_at' => $now,
            'contacted_at' => $prospect->contacted_at ?: $now,
            'next_follow_up_at' => $days > 0 ? $now->copy()->addDays($days) : null,
            'follow_up_snoozed_until' => null,
            'follow_up_count' => ((int) $prospect->follow_up_count) + 1,
            'last_follow_up_message' => $data['message'],
        ]);

        if (in_array($prospect->status, ['baru', 'diriset'], true)) {
            $stages->transition($prospect, 'dihubungi', $request->user(), 'Follow-up WhatsApp');
        }

        return back()->with('success', 'Follow-up dicatat sebagai sudah dikirim.');
    }

    public function snooze(Request $request, Prospect $prospect): RedirectResponse
    {
        $prospect = $this->visibleProspect($request, $prospect);
        $data = $request->validate(['until' => ['required', 'date', 'after:now']]);
        $prospect->update(['follow_up_snoozed_until' => $data['until']]);
        return back()->with('success', 'Tugas follow-up ditunda.');
    }

    private function visibleProspect(Request $request, Prospect $prospect): Prospect
    {
        abort_unless(Prospect::query()->visibleTo($request->user())->whereKey($prospect->id)->exists(), 403);
        return $prospect;
    }
}
