<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProspectResource;
use App\Models\Prospect;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\SalesPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        SalesPerformanceService $performance,
        FollowUpService $followUps
    ): Response {
        $user = $request->user();
        $base = Prospect::query()->visibleTo($user);
        $openStatuses = array_diff(array_keys(Prospect::STATUSES), ['deal', 'ditolak', 'tidak_cocok']);

        $followUpQueue = $followUps->queue($user);
        $followUpTaskCount = collect($followUpQueue)
            ->sum(fn ($items) => $items->count());

        $stats = [
            'total' => (clone $base)->count(),
            'priority_high' => (clone $base)->where('priority', 'tinggi')->count(),
            'need_followup' => $followUpTaskCount,
            'meeting_plus' => (clone $base)->whereIn('status', ['meeting', 'demo', 'proposal', 'negosiasi'])->count(),
            'deals' => (clone $base)->where('status', 'deal')->count(),
            'pipeline_value' => (float) (clone $base)->whereNotIn('status', ['deal', 'ditolak', 'tidak_cocok'])->sum('estimated_deal_value'),
        ];

        $pipeline = collect(Prospect::STATUSES)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'count' => (clone $base)->where('status', $key)->count(),
        ])->values();

        $scheduledFollowUps = (clone $base)
            ->with('assignedUser')
            ->whereIn('status', $openStatuses)
            ->whereNotNull('next_follow_up_at')
            ->orderBy('next_follow_up_at')
            ->limit(6)
            ->get();

        $topProspects = (clone $base)
            ->with('assignedUser')
            ->orderByDesc('total_score')
            ->orderByDesc('estimated_deal_value')
            ->limit(6)
            ->get();

        $month = now()->month;
        $year = now()->year;

        $salesPerformance = !$user->isAdmin()
            ? $performance->forUser($user, $year, $month)
            : null;

        $teamPerformance = $user->isAdmin()
            ? User::query()
                ->where('role', 'sales')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($sales) => [
                    'user' => ['id' => $sales->id, 'name' => $sales->name],
                    'performance' => $performance->forUser($sales, $year, $month),
                ])
                ->values()
            : [];

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'pipeline' => $pipeline,
            'followUps' => ProspectResource::collection($scheduledFollowUps)->resolve(),
            'topProspects' => ProspectResource::collection($topProspects)->resolve(),
            'salesPerformance' => $salesPerformance,
            'teamPerformance' => $teamPerformance,
        ]);
    }
}
