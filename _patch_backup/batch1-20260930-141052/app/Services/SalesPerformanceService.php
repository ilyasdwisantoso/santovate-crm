<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\CarbonImmutable;

class SalesPerformanceService
{
    public function forUser(User $user, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
        $end = $start->endOfMonth();
        $target = SalesTarget::firstOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            ['target_contacted' => 20, 'target_meetings' => 8, 'target_proposals' => 4, 'target_deals' => 1, 'target_revenue' => 15000000]
        );

        $owned = Prospect::query()->where('assigned_to', $user->id);
        $actual = [
            'contacted' => (clone $owned)->whereBetween('contacted_at', [$start, $end])->count(),
            'meetings' => (clone $owned)->whereBetween('meeting_at', [$start, $end])->count(),
            'proposals' => (clone $owned)->whereBetween('proposal_at', [$start, $end])->count(),
            'deals' => (clone $owned)->whereBetween('deal_at', [$start, $end])->count(),
            'revenue' => (float) (clone $owned)->whereBetween('deal_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(COALESCE(actual_deal_value, estimated_deal_value)), 0) AS total')->value('total'),
            'active_companies' => (clone $owned)->whereNotIn('status', ['deal','ditolak','tidak_cocok'])->count(),
            'overdue_followups' => (clone $owned)->whereNotIn('status', ['deal','ditolak','tidak_cocok'])
                ->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now())->count(),
        ];

        $goals = [
            'contacted' => (int) $target->target_contacted,
            'meetings' => (int) $target->target_meetings,
            'proposals' => (int) $target->target_proposals,
            'deals' => (int) $target->target_deals,
            'revenue' => (float) $target->target_revenue,
        ];

        $progress = [];
        foreach ($goals as $key => $goal) {
            $progress[$key] = $goal > 0 ? min(100, round(($actual[$key] / $goal) * 100)) : 0;
        }

        // Leading indicators keep sales execution healthy, but deals/revenue carry the most weight.
        $score = round(
            $progress['contacted'] * 0.10 +
            $progress['meetings'] * 0.20 +
            $progress['proposals'] * 0.20 +
            $progress['deals'] * 0.20 +
            $progress['revenue'] * 0.30
        );

        return [
            'target_id' => $target->id,
            'period' => ['year' => $year, 'month' => $month, 'label' => $start->translatedFormat('F Y')],
            'actual' => $actual,
            'goals' => $goals,
            'progress' => $progress,
            'score' => min(100, $score),
        ];
    }
}
