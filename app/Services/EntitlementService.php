<?php

namespace App\Services;

use App\Models\EntitlementGrant;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EntitlementService
{
    public function snapshot(Organization $organization, ?array $usage = null, ?Subscription $subscription = null): array
    {
        $subscription ??= $this->activeSubscription($organization);
        $plan = $subscription?->plan;
        $configuration = $subscription?->businessConfiguration ?: $organization->businessConfiguration;
        $internal = $organization->slug === 'santovate-internal' || (bool)data_get($organization->settings,'internal',false);

        $limits = [
            'users'=>(int)($plan?->user_limit ?? 0),
            'prospects'=>(int)($plan?->prospect_limit ?? 0),
        ];

        $knownFeatures = array_keys(config('entitlements.feature_labels', []));
        $features = array_fill_keys($knownFeatures, false);
        foreach ((array)($plan?->features ?? []) as $key) $features[(string)$key] = true;
        foreach ((array)($configuration?->entitlement_features ?? []) as $key) $features[(string)$key] = true;

        $overrides = (array)($subscription?->entitlement_overrides ?? []);
        foreach ((array)data_get($overrides,'limits',[]) as $key=>$value) {
            if (array_key_exists($key,$limits) && $value !== null) $limits[$key] = max(0,(int)$value);
        }
        foreach ((array)data_get($overrides,'features',[]) as $key=>$value) $features[(string)$key] = (bool)$value;

        $grants = $this->activeGrants($organization,$subscription);
        foreach ($grants as $grant) {
            if ($grant->kind === 'feature') {
                if ($grant->operation === 'disable') $features[$grant->key] = false;
                elseif (in_array($grant->operation,['enable','set'],true)) $features[$grant->key] = (bool)($grant->boolean_value ?? true);
                continue;
            }
            if (!array_key_exists($grant->key,$limits)) continue;
            if ($grant->operation === 'set') $limits[$grant->key] = max(0,(int)($grant->integer_value ?? 0));
            elseif ($grant->operation === 'add') $limits[$grant->key] = max(0,$limits[$grant->key] + (int)($grant->integer_value ?? 0));
        }

        $usage ??= [
            'users'=>$organization->users()->where('is_active',true)->count(),
            'prospects'=>$organization->prospects()->count(),
        ];

        $resourcePayload = [];
        foreach ($limits as $key=>$limit) {
            $used = max(0,(int)($usage[$key] ?? 0));
            $unlimited = $internal;
            $effectiveLimit = $unlimited ? null : max(0,(int)$limit);
            $remaining = $unlimited ? null : max(0,$effectiveLimit-$used);
            $resourcePayload[$key] = [
                'key'=>$key,
                'label'=>config("entitlements.resources.{$key}", ucfirst($key)),
                'limit'=>$effectiveLimit,
                'used'=>$used,
                'remaining'=>$remaining,
                'unlimited'=>$unlimited,
                'reached'=>!$unlimited && $used >= $effectiveLimit,
                'usage_percent'=>$unlimited || $effectiveLimit <= 0 ? 0 : min(100,round($used/$effectiveLimit*100,1)),
            ];
        }

        return [
            'active'=>(bool)$subscription,
            'internal'=>$internal,
            'subscription'=>$subscription ? [
                'id'=>$subscription->id,
                'status'=>$subscription->status,
                'billing_cycle'=>$subscription->billing_cycle,
                'starts_at'=>$subscription->starts_at?->toIso8601String(),
                'ends_at'=>$subscription->ends_at?->toIso8601String(),
                'plan'=>$plan?->only(['id','key','name']),
            ] : null,
            'configuration'=>$configuration ? [
                'id'=>$configuration->id,'key'=>$configuration->key,'name'=>$configuration->name,
            ] : null,
            'limits'=>$resourcePayload,
            'features'=>$features,
            'enabled_features'=>collect($features)->filter()->keys()->values()->all(),
            'grants'=>[
                'active_count'=>$grants->count(),
                'limit_count'=>$grants->where('kind','limit')->count(),
                'feature_count'=>$grants->where('kind','feature')->count(),
            ],
        ];
    }

    public function allowsFeature(Organization $organization, string $feature): bool
    {
        return (bool)data_get($this->snapshot($organization),"features.{$feature}",false);
    }

    public function canConsume(Organization $organization, string $resource, int $quantity = 1): bool
    {
        $capacity = data_get($this->snapshot($organization),"limits.{$resource}");
        if (!$capacity) return false;
        if ($capacity['unlimited']) return true;
        return ((int)$capacity['used'] + max(0,$quantity)) <= (int)$capacity['limit'];
    }

    public function assertCanConsume(Organization $organization, string $resource, int $quantity = 1): void
    {
        if ($this->canConsume($organization,$resource,$quantity)) return;
        throw ValidationException::withMessages([
            'entitlement'=>$this->capacityMessage($organization,$resource,$quantity),
        ]);
    }

    public function capacityMessage(Organization $organization, string $resource, int $quantity = 1): string
    {
        $capacity = data_get($this->snapshot($organization),"limits.{$resource}");
        if (!$capacity) return 'Entitlement resource tidak dikenali.';
        $label = $capacity['label'] ?? ucfirst($resource);
        $limit = $capacity['limit'] ?? 0;
        $used = $capacity['used'] ?? 0;
        return "Kapasitas {$label} tidak mencukupi. Pemakaian {$used} dari {$limit}; kebutuhan tambahan {$quantity}.";
    }

    private function activeSubscription(Organization $organization): ?Subscription
    {
        if ($organization->relationLoaded('subscriptions')) {
            $subscription = $organization->subscriptions
                ->first(fn ($row) => $row->status === 'active' && (!$row->ends_at || $row->ends_at->isFuture()));
            if ($subscription) {
                $subscription->loadMissing(['plan','businessConfiguration']);
                return $subscription;
            }
        }
        return $organization->activeSubscription();
    }

    private function activeGrants(Organization $organization, ?Subscription $subscription): Collection
    {
        if ($organization->relationLoaded('entitlementGrants')) {
            return $organization->entitlementGrants
                ->filter(fn ($grant) => $grant->status === 'active')
                ->filter(fn ($grant) => !$grant->starts_at || $grant->starts_at->lte(now()))
                ->filter(fn ($grant) => !$grant->ends_at || $grant->ends_at->gt(now()))
                ->filter(fn ($grant) => $grant->subscription_id === null || (int)$grant->subscription_id === (int)$subscription?->id)
                ->sortBy('id')->values();
        }

        return EntitlementGrant::query()->active()
            ->where('organization_id',$organization->id)
            ->where(fn ($q) => $q->whereNull('subscription_id')->when($subscription,fn($x)=>$x->orWhere('subscription_id',$subscription->id)))
            ->orderBy('id')->get();
    }
}
