<?php
namespace App\Services;
use App\Models\BusinessConfiguration;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
class SubscriptionService {
    public function __construct(private readonly BusinessConfigurationService $configs) {}
    public function activate(Subscription $subscription): Subscription {
        return DB::transaction(function () use ($subscription) {
            $subscription->load(['organization','businessConfiguration','plan']);
            $cycle=$subscription->billing_cycle;
            $start=now();
            $end=$cycle==='annual' ? $start->copy()->addYear() : $start->copy()->addMonth();
            $subscription->organization->subscriptions()->where('id','!=',$subscription->id)->where('status','active')->update(['status'=>'expired']);
            $subscription->update(['status'=>'active','starts_at'=>$start,'ends_at'=>$end,'activated_at'=>$start]);
            if ($subscription->businessConfiguration) $this->configs->activate($subscription->organization,$subscription->businessConfiguration);
            return $subscription->refresh();
        });
    }
}
