<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BusinessConfiguration;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionAddonOrder;
use App\Models\SubscriptionAddonPayment;
use Illuminate\Support\Collection;

class PlatformAnalyticsService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function dashboard(): array
    {
        $clients = $this->realClientOrganizations();
        $clientIds = $clients->pluck('id');
        $active = $this->activeSubscriptions($clientIds);
        $addonOrders = $this->activeAddonOrders($clientIds);

        $subscriptionMrr = $active->sum(fn ($subscription) => $this->monthlyEquivalent($subscription));
        $addonMrr = $addonOrders->sum(fn ($order) => $this->monthlyAddonEquivalent($order));
        $mrr = $subscriptionMrr + $addonMrr;

        $subscriptionCashMtd = Payment::query()->whereIn('organization_id',$clientIds)->where('status','paid')
            ->whereBetween('paid_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('amount');
        $addonCashMtd = SubscriptionAddonPayment::query()->whereIn('organization_id',$clientIds)->where('status','paid')
            ->whereBetween('paid_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('amount');
        $cashMtd = (int)$subscriptionCashMtd + (int)$addonCashMtd;

        $configurationBreakdown = BusinessConfiguration::query()->orderBy('sort_order')->get()
            ->map(function (BusinessConfiguration $config) use ($active,$addonOrders) {
                $rows = $active->where('business_configuration_id',$config->id);
                $baseMrr = $rows->sum(fn ($subscription) => $this->monthlyComponent($subscription,'base_amount'));
                $configurationMrr = $rows->sum(fn ($subscription) => $this->monthlyComponent($subscription,'configuration_amount'));
                $subscriptionIds = $rows->pluck('id');
                $configAddonOrders = $addonOrders->whereIn('subscription_id',$subscriptionIds);
                $addonMrr = $configAddonOrders->sum(fn($order)=>$this->monthlyAddonEquivalent($order));
                $mrr = $baseMrr + $configurationMrr + $addonMrr;
                return [
                    'id'=>$config->id,'key'=>$config->key,'name'=>$config->name,'industry'=>$config->industry,
                    'description'=>$config->description,'sort_order'=>(int)$config->sort_order,
                    'monthly_addon_price'=>(int)$config->monthly_addon_price,'annual_addon_price'=>(int)$config->annual_addon_price,
                    'is_active'=>(bool)$config->is_active,
                    'clients'=>$rows->pluck('organization_id')->unique()->count(),
                    'base_mrr'=>(int)round($baseMrr),'configuration_mrr'=>(int)round($configurationMrr),
                    'addon_mrr'=>(int)round($addonMrr),'active_addons'=>$configAddonOrders->count(),
                    'mrr'=>(int)round($mrr),'arr'=>(int)round($mrr*12),
                ];
            })->values();

        $planMix = $active->groupBy(fn ($subscription) => $subscription->plan?->name ?: 'Unknown')
            ->map(function($rows,$name) use($addonOrders){
                $subscriptionIds=$rows->pluck('id');
                $base=$rows->sum(fn($s)=>$this->monthlyEquivalent($s));
                $addons=$addonOrders->whereIn('subscription_id',$subscriptionIds)->sum(fn($o)=>$this->monthlyAddonEquivalent($o));
                return ['name'=>$name,'count'=>$rows->count(),'mrr'=>(int)round($base+$addons),'addon_mrr'=>(int)round($addons)];
            })->values();

        $subscriptionPayments = Payment::query()->whereIn('organization_id',$clientIds)
            ->with(['organization:id,name','subscription.plan:id,name'])->latest('id')->limit(8)->get()->map(fn ($payment) => [
                'key'=>'subscription-'.$payment->id,'organization'=>$payment->organization?->name,
                'label'=>$payment->subscription?->plan?->name ?: 'Subscription','kind'=>'subscription',
                'status'=>$payment->status,'amount'=>(int)$payment->amount,'provider'=>$payment->provider,
                'paid_at'=>$payment->paid_at?->toIso8601String(),'created_at'=>$payment->created_at?->toIso8601String(),
            ]);
        $addonPayments = SubscriptionAddonPayment::query()->whereIn('organization_id',$clientIds)
            ->with(['organization:id,name','order.addon:id,name'])->latest('id')->limit(8)->get()->map(fn($payment)=>[
                'key'=>'addon-'.$payment->id,'organization'=>$payment->organization?->name,
                'label'=>'Add-on · '.($payment->order?->addon?->name ?: 'Capacity'),'kind'=>'addon',
                'status'=>$payment->status,'amount'=>(int)$payment->amount,'provider'=>$payment->provider,
                'paid_at'=>$payment->paid_at?->toIso8601String(),'created_at'=>$payment->created_at?->toIso8601String(),
            ]);
        $recentPayments = $subscriptionPayments->concat($addonPayments)
            ->sortByDesc(fn($row)=>$row['paid_at'] ?: $row['created_at'])->take(8)->values();

        return [
            'metrics'=>[
                'mrr'=>(int)round($mrr),'arr'=>(int)round($mrr*12),'addon_mrr'=>(int)round($addonMrr),
                'active_addons'=>$addonOrders->count(),
                'active_clients'=>$active->pluck('organization_id')->unique()->count(),'active_subscriptions'=>$active->count(),
                'cash_collected_mtd'=>$cashMtd,
                'pending_payments'=>Payment::query()->whereIn('organization_id',$clientIds)->where('status','pending')->count()
                    + SubscriptionAddonPayment::query()->whereIn('organization_id',$clientIds)->where('status','pending')->count(),
                'pending_approvals'=>ApprovalRequest::query()->where('approval_scope','platform')->where('status','pending')->count(),
            ],
            'configuration_breakdown'=>$configurationBreakdown,'plan_mix'=>$planMix,'recent_payments'=>$recentPayments,
        ];
    }

    public function clients(): Collection
    {
        return Organization::query()->with([
                'businessConfiguration:id,key,name,entitlement_features',
                'subscriptions'=>fn($q)=>$q->with(['plan:id,key,name,user_limit,prospect_limit,features','businessConfiguration:id,key,name,entitlement_features'])->latest('id'),
                'entitlementGrants'=>fn($q)=>$q->active()->orderBy('id'),
            ])
            ->withCount(['users as active_users_count'=>fn($q)=>$q->where('is_active',true),'prospects as prospects_count'])
            ->latest('id')->get()->reject(fn ($organization) => $this->isInternal($organization))
            ->map(function ($organization) {
                $subscription = $organization->subscriptions->first(fn($s)=>$s->status==='active' && (!$s->ends_at || $s->ends_at->isFuture())) ?: $organization->subscriptions->first();
                $snapshot = $this->entitlements->snapshot($organization,['users'=>(int)$organization->active_users_count,'prospects'=>(int)$organization->prospects_count],$subscription && $subscription->status === 'active' ? $subscription : null);
                $addonRows = $subscription ? SubscriptionAddonOrder::query()->where('subscription_id',$subscription->id)->where('status','activated')->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))->get() : collect();
                return [
                    'id'=>$organization->id,'name'=>$organization->name,'slug'=>$organization->slug,'status'=>$organization->status,
                    'is_demo'=>(bool)data_get($organization->settings,'demo',false),'users_count'=>(int)$organization->active_users_count,'prospects_count'=>(int)$organization->prospects_count,
                    'configuration'=>$organization->businessConfiguration?->only(['id','key','name']),
                    'subscription'=>$subscription ? ['id'=>$subscription->id,'status'=>$subscription->status,'billing_cycle'=>$subscription->billing_cycle,'total_amount'=>(int)$subscription->total_amount,'ends_at'=>$subscription->ends_at?->toIso8601String(),'plan'=>$subscription->plan?->only(['id','key','name','user_limit','prospect_limit'])] : null,
                    'entitlements'=>['users'=>$snapshot['limits']['users'],'prospects'=>$snapshot['limits']['prospects'],'enabled_features'=>count($snapshot['enabled_features']),'active_grants'=>$snapshot['grants']['active_count']],
                    'addons'=>['active_count'=>$addonRows->count(),'mrr'=>(int)round($addonRows->sum(fn($o)=>$this->monthlyAddonEquivalent($o)))],
                ];
            })->values();
    }

    public function configurationAnalytics(): Collection { return collect($this->dashboard()['configuration_breakdown']); }

    private function realClientOrganizations(): Collection
    {
        return Organization::query()->get(['id','slug','settings'])->reject(fn ($org) => $this->isInternal($org) || (bool)data_get($org->settings,'demo',false))->values();
    }

    private function activeSubscriptions(Collection $clientIds): Collection
    {
        if ($clientIds->isEmpty()) return collect();
        return Subscription::query()->whereIn('organization_id',$clientIds)->where('status','active')->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))->with(['plan:id,key,name','businessConfiguration:id,key,name'])->get();
    }

    private function activeAddonOrders(Collection $clientIds): Collection
    {
        if ($clientIds->isEmpty()) return collect();
        return SubscriptionAddonOrder::query()->whereIn('organization_id',$clientIds)->where('status','activated')->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))->with(['subscription:id,subscription_plan_id,business_configuration_id,status,billing_cycle,ends_at','addon:id,key,name'])->get();
    }

    private function monthlyEquivalent(Subscription $subscription): float { return $this->monthlyComponent($subscription,'total_amount'); }
    private function monthlyComponent(Subscription $subscription,string $field): float { $amount=(float)$subscription->{$field}; return $subscription->billing_cycle==='annual'?$amount/12:$amount; }
    private function monthlyAddonEquivalent(SubscriptionAddonOrder $order): float { $amount=(float)$order->total_amount; return $order->billing_cycle==='annual'?$amount/12:$amount; }
    private function isInternal(Organization $organization): bool { return $organization->slug==='santovate-internal'||(bool)data_get($organization->settings,'internal',false); }
}
