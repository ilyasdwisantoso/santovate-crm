<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use Carbon\Carbon;

class SalesMonitoringService
{
    public function __construct(private readonly FollowUpService $followUps) {}

    public function snapshot(User $admin): array
    {
        if (!$admin->isAdmin()) return [];

        $organizationId = (int) $admin->organization_id;
        $leadAgeDays = max(1, $this->followUps->leadAgeDays($admin));
        $closed = ['deal', 'ditolak', 'tidak_cocok'];

        return User::query()
            ->where('organization_id', $organizationId)
            ->where('role', 'sales')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id','name','email','profile_initials','last_login_at'])
            ->map(function (User $sales) use ($organizationId, $leadAgeDays, $closed) {
                $base = Prospect::query()
                    ->where('organization_id', $organizationId)
                    ->where('assigned_to', $sales->id);

                $assigned = (clone $base)->count();
                $untouched = (clone $base)
                    ->whereNull('last_contact_at')
                    ->whereIn('status', ['baru','diriset'])
                    ->count();
                $worked = max(0, $assigned - $untouched);
                $contacted = (clone $base)
                    ->where(fn ($q) => $q
                        ->whereNotNull('last_contact_at')
                        ->orWhereNotIn('status', ['baru','diriset']))
                    ->count();
                $responded = (clone $base)
                    ->whereIn('status', ['membalas','meeting','demo','proposal','negosiasi','deal'])
                    ->count();
                $meetingPlus = (clone $base)
                    ->whereIn('status', ['meeting','demo','proposal','negosiasi','deal'])
                    ->count();
                $won = (clone $base)->where('status', 'deal')->count();
                $lost = (clone $base)->whereIn('status', ['ditolak','tidak_cocok'])->count();
                $overdue = (clone $base)
                    ->whereNotIn('status', $closed)
                    ->whereNotNull('next_follow_up_at')
                    ->where('next_follow_up_at', '<=', now()->endOfDay())
                    ->count();
                $stale = (clone $base)
                    ->whereNotIn('status', $closed)
                    ->whereNull('last_outbound_at')
                    ->whereNull('last_feedback_at')
                    ->where('created_at', '<=', now()->subDays($leadAgeDays))
                    ->count();

                $pipelineValue = (float) (clone $base)
                    ->whereNotIn('status', $closed)
                    ->sum('estimated_deal_value');

                $wonValue = (float) Deal::query()
                    ->where('organization_id', $organizationId)
                    ->where('owner_id', $sales->id)
                    ->where('status', 'won')
                    ->sum('actual_deal_value');

                $lastActivity = ProspectActivity::query()
                    ->where('user_id', $sales->id)
                    ->whereHas('prospect', fn ($q) => $q
                        ->where('organization_id', $organizationId)
                        ->where('assigned_to', $sales->id))
                    ->max('occurred_at');

                return [
                    'user' => [
                        'id' => $sales->id,
                        'name' => $sales->name,
                        'email' => $sales->email,
                        'profile_initials' => $sales->profile_initials,
                    ],
                    'assigned' => $assigned,
                    'worked' => $worked,
                    'untouched' => $untouched,
                    'contacted' => $contacted,
                    'responded' => $responded,
                    'meeting_plus' => $meetingPlus,
                    'won' => $won,
                    'lost' => $lost,
                    'overdue' => $overdue,
                    'stale' => $stale,
                    'lead_age_days' => $leadAgeDays,
                    'progress_percent' => $assigned > 0 ? round($worked / $assigned * 100, 1) : 0,
                    'contact_rate' => $assigned > 0 ? round($contacted / $assigned * 100, 1) : 0,
                    'conversion_rate' => $assigned > 0 ? round($won / $assigned * 100, 1) : 0,
                    'pipeline_value' => $pipelineValue,
                    'won_value' => $wonValue,
                    'last_activity' => $lastActivity ? Carbon::parse($lastActivity)->toIso8601String() : null,
                    'last_login_at' => $sales->last_login_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
