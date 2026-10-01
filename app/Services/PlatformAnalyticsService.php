<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BusinessConfiguration;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class PlatformAnalyticsService
{
    public function dashboard(): array
    {
        $clients = $this->realClientOrganizations();
        $clientIds = $clients->pluck('id');
        $active = $this->activeSubscriptions($clientIds);

        $mrr = $active->sum(fn ($subscription) => $this->monthlyEquivalent($subscription));
        $cashMtd = Payment::query()
            ->whereIn('organization_id',$clientIds)
            ->where('status','paid')
            ->whereBetween('paid_at',[now()->startOfMonth(),now()->endOfMonth()])
            ->sum('amount');

        $configurationBreakdown = BusinessConfiguration::query()->orderBy('sort_order')->get()
            ->map(function (BusinessConfiguration $config) use ($active) {
                $rows = $active->where('business_configuration_id',$config->id);
                $mrr = $rows->sum(fn ($subscription) => $this->monthlyEquivalent($subscription));
                $baseMrr = $rows->sum(fn ($subscription) => $this->monthlyComponent($subscription,'base_amount'));
                $configurationMrr = $rows->sum(fn ($subscription) => $this->monthlyComponent($subscription,'configuration_amount'));
                return [
                    'id'=>$config->id,'key'=>$config->key,'name'=>$config->name,'industry'=>$config->industry,
                    'description'=>$config->description,'sort_order'=>(int)$config->sort_order,
                    'monthly_addon_price'=>(int)$config->monthly_addon_price,'annual_addon_price'=>(int)$config->annual_addon_price,
                    'is_active'=>(bool)$config->is_active,
                    'clients'=>$rows->pluck('organization_id')->unique()->count(),
                    'base_mrr'=>(int)round($baseMrr),'configuration_mrr'=>(int)round($configurationMrr),
                    'mrr'=>(int)round($mrr),'arr'=>(int)round($mrr*12),
                ];
            })->values();

        $planMix = $active->groupBy(fn ($subscription) => $subscription->plan?->name ?: 'Unknown')
            ->map(fn ($rows,$name) => ['name'=>$name,'count'=>$rows->count(),'mrr'=>(int)round($rows->sum(fn ($s)=>$this->monthlyEquivalent($s)))])
            ->values();

        $recentPayments = Payment::query()->whereIn('organization_id',$clientIds)
            ->with(['organization:id,name','subscription.plan:id,name'])
            ->latest('id')->limit(8)->get()->map(fn ($payment) => [
                'id'=>$payment->id,'organization'=>$payment->organization?->name,'plan'=>$payment->subscription?->plan?->name,
                'status'=>$payment->status,'amount'=>(int)$payment->amount,'provider'=>$payment->provider,
                'paid_at'=>$payment->paid_at?->toIso8601String(),'created_at'=>$payment->created_at?->toIso8601String(),
            ])->values();

        return [
            'metrics'=>[
                'mrr'=>(int)round($mrr),
                'arr'=>(int)round($mrr*12),
                'active_clients'=>$active->pluck('organization_id')->unique()->count(),
                'active_subscriptions'=>$active->count(),
                'cash_collected_mtd'=>(int)$cashMtd,
                'pending_payments'=>Payment::query()->whereIn('organization_id',$clientIds)->where('status','pending')->count(),
                'pending_approvals'=>ApprovalRequest::query()->where('approval_scope','platform')->where('status','pending')->count(),
            ],
            'configuration_breakdown'=>$configurationBreakdown,
            'plan_mix'=>$planMix,
            'recent_payments'=>$recentPayments,
        ];
    }

    public function clients(): Collection
    {
        return Organization::query()->with(['businessConfiguration:id,key,name','subscriptions'=>fn($q)=>$q->with('plan:id,key,name,user_limit')->latest('id')])
            ->withCount('users')->latest('id')->get()
            ->reject(fn ($organization) => $this->isInternal($organization))
            ->map(function ($organization) {
                $subscription = $organization->subscriptions->first(fn($s)=>$s->status==='active' && (!$s->ends_at || $s->ends_at->isFuture()))
                    ?: $organization->subscriptions->first();
                return [
                    'id'=>$organization->id,'name'=>$organization->name,'slug'=>$organization->slug,'status'=>$organization->status,
                    'is_demo'=>(bool)data_get($organization->settings,'demo',false),'users_count'=>$organization->users_count,
                    'configuration'=>$organization->businessConfiguration?->only(['id','key','name']),
                    'subscription'=>$subscription ? [
                        'id'=>$subscription->id,'status'=>$subscription->status,'billing_cycle'=>$subscription->billing_cycle,
                        'total_amount'=>(int)$subscription->total_amount,'ends_at'=>$subscription->ends_at?->toIso8601String(),
                        'plan'=>$subscription->plan?->only(['id','key','name','user_limit']),
                    ] : null,
                ];
            })->values();
    }

    public function configurationAnalytics(): Collection
    {
        return collect($this->dashboard()['configuration_breakdown']);
    }

    private function realClientOrganizations(): Collection
    {
        return Organization::query()->get(['id','slug','settings'])
            ->reject(fn ($org) => $this->isInternal($org) || (bool)data_get($org->settings,'demo',false))
            ->values();
    }

    private function activeSubscriptions(Collection $clientIds): Collection
    {
        if ($clientIds->isEmpty()) return collect();
        return Subscription::query()->whereIn('organization_id',$clientIds)->where('status','active')
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))
            ->with(['plan:id,key,name','businessConfiguration:id,key,name'])
            ->get();
    }

    private function monthlyEquivalent(Subscription $subscription): float
    {
        return $this->monthlyComponent($subscription,'total_amount');
    }

    private function monthlyComponent(Subscription $subscription, string $field): float
    {
        $amount = (float)$subscription->{$field};
        return $subscription->billing_cycle === 'annual' ? $amount / 12 : $amount;
    }

    private function isInternal(Organization $organization): bool
    {
        return $organization->slug === 'santovate-internal' || (bool)data_get($organization->settings,'internal',false);
    }
}
