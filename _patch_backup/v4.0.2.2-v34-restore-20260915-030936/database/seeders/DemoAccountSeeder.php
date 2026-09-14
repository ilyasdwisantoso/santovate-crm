<?php

namespace Database\Seeders;

use App\Models\BusinessConfiguration;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BusinessConfigurationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SaasFoundationSeeder::class);

        $workspaces = [
            'software-agency' => ['slug'=>'demo-software-agency','name'=>'Demo Software & Agency','plan'=>'growth'],
            'logistics-freight' => ['slug'=>'demo-logistics-freight','name'=>'Demo Logistics & Freight','plan'=>'starter'],
            'distributor-b2b' => ['slug'=>'demo-distributor-b2b','name'=>'Demo Distributor & B2B','plan'=>'starter'],
            'parfum-retail' => ['slug'=>'demo-parfum-retail','name'=>'Demo Parfum & Retail','plan'=>'starter'],
        ];

        $organizations = [];
        foreach ($workspaces as $configKey => $meta) {
            $config = BusinessConfiguration::where('key', $configKey)->firstOrFail();
            $plan = SubscriptionPlan::where('key', $meta['plan'])->firstOrFail();

            $organization = Organization::updateOrCreate(
                ['slug'=>$meta['slug']],
                [
                    'name'=>$meta['name'],
                    'business_configuration_id'=>$config->id,
                    'status'=>'active',
                    'settings'=>['demo'=>true],
                ]
            );

            $base = $plan->monthly_price;
            $addon = $config->monthly_addon_price;
            Subscription::updateOrCreate(
                ['organization_id'=>$organization->id, 'status'=>'active'],
                [
                    'subscription_plan_id'=>$plan->id,
                    'business_configuration_id'=>$config->id,
                    'billing_cycle'=>'monthly',
                    'base_amount'=>$base,
                    'configuration_amount'=>$addon,
                    'total_amount'=>$base+$addon,
                    'starts_at'=>now(),
                    'ends_at'=>now()->addYears(5),
                    'activated_at'=>now(),
                ]
            );

            app(BusinessConfigurationService::class)->activate($organization->fresh(), $config);
            $organizations[$configKey] = $organization->fresh();
        }

        // Requested demo accounts. Running this seeder resets password, role,
        // active status and workspace assignment without deleting historical rows.
        $accounts = [
            ['email'=>'admin@santovate.com','name'=>'Santovate Admin','password'=>'Santovate123!','role'=>'admin','config'=>'software-agency'],
            ['email'=>'sales@santovate.com','name'=>'Santovate Sales','password'=>'Sales123!','role'=>'sales','config'=>'software-agency'],
            ['email'=>'ahmadmazkur@santovate.com','name'=>'Ahmad Mazkur','password'=>'Mazkur!@#','role'=>'sales','config'=>'software-agency'],
            ['email'=>'demo.logistics@santovate.com','name'=>'Demo Logistics Admin','password'=>'DemoLogistics123!','role'=>'admin','config'=>'logistics-freight'],
            ['email'=>'demo.distributor@santovate.com','name'=>'Demo Distributor Admin','password'=>'DemoDistributor123!','role'=>'admin','config'=>'distributor-b2b'],
            ['email'=>'demo.parfum@santovate.com','name'=>'Demo Parfum Admin','password'=>'DemoParfum123!','role'=>'admin','config'=>'parfum-retail'],
        ];

        foreach ($accounts as $account) {
            $organization = $organizations[$account['config']];
            User::updateOrCreate(
                ['email'=>$account['email']],
                [
                    'organization_id'=>$organization->id,
                    'name'=>$account['name'],
                    'password'=>$account['password'],
                    'role'=>$account['role'],
                    'is_active'=>true,
                ]
            );
        }

        // Repair only orphaned tenant references; existing non-null tenant data
        // stays untouched so this is safe for the already-deployed Hostinger DB.
        $softwareOrg = $organizations['software-agency'];
        User::whereNull('organization_id')->update(['organization_id'=>$softwareOrg->id]);
        DB::table('prospects')->whereNull('organization_id')->update(['organization_id'=>$softwareOrg->id]);
        DB::table('import_batches')->whereNull('organization_id')->update(['organization_id'=>$softwareOrg->id]);
        DB::table('sales_targets')->whereNull('organization_id')->update(['organization_id'=>$softwareOrg->id]);

        // Remove invalid cross-workspace assignment links that can cause wrong data visibility.
        User::query()->select(['id','organization_id'])->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('prospects')
                    ->where('assigned_to', $user->id)
                    ->whereNotNull('organization_id')
                    ->where('organization_id', '!=', $user->organization_id)
                    ->update(['assigned_to'=>null]);
            }
        });
    }
}
