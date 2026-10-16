<?php
namespace Tests\Support;
use App\Models\{Organization,Subscription,SubscriptionPlan,User};
trait NativeCrmFixtures {
    protected function nativeActor(string $key, int $limit=10): User {
        $org=Organization::create(['name'=>'Fixture '.$key,'slug'=>'fixture-'.$key]);
        $plan=SubscriptionPlan::create(['key'=>'fixture-'.$key,'name'=>'Fixture','user_limit'=>10,'prospect_limit'=>$limit,'features'=>['crm','whatsapp_api','imports','targets']]);
        Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'status'=>'active','ends_at'=>now()->addMonth(),'activated_at'=>now()]);
        return User::create(['organization_id'=>$org->id,'name'=>'Fixture Admin','email'=>$key.'@example.test','password'=>'test-password','role'=>'admin','is_active'=>true]);
    }
}
