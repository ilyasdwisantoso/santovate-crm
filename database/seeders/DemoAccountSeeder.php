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
            'software-agency' => [
                'slug' => 'demo-software-agency',
                'name' => 'Demo Software & Agency',
                'plan' => 'growth',
            ],
            'logistics-freight' => [
                'slug' => 'demo-logistics-freight',
                'name' => 'Demo Logistics & Freight',
                'plan' => 'growth',
            ],
            'distributor-b2b' => [
                'slug' => 'demo-distributor-b2b',
                'name' => 'Demo Distributor & B2B',
                'plan' => 'growth',
            ],
            'parfum-retail' => [
                'slug' => 'demo-parfum-retail',
                'name' => 'Demo Parfum & Retail',
                'plan' => 'growth',
            ],
        ];

        $organizations = [];

        foreach ($workspaces as $configKey => $meta) {
            $config = BusinessConfiguration::where('key', $configKey)->firstOrFail();
            $plan = SubscriptionPlan::where('key', $meta['plan'])->firstOrFail();

            $organization = Organization::updateOrCreate(
                ['slug' => $meta['slug']],
                [
                    'name' => $meta['name'],
                    'business_configuration_id' => $config->id,
                    'status' => 'active',
                    'settings' => ['demo' => true],
                ]
            );

            $base = $plan->monthly_price;
            $addon = $config->monthly_addon_price;

            Subscription::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'status' => 'active',
                ],
                [
                    'subscription_plan_id' => $plan->id,
                    'business_configuration_id' => $config->id,
                    'billing_cycle' => 'monthly',
                    'base_amount' => $base,
                    'configuration_amount' => $addon,
                    'total_amount' => $base + $addon,
                    'starts_at' => now(),
                    'ends_at' => now()->addYears(5),
                    'activated_at' => now(),
                ]
            );

            app(BusinessConfigurationService::class)->activate(
                $organization->fresh(),
                $config
            );

            $organizations[$configKey] = $organization->fresh();
        }

        /*
         * Demo / employee accounts.
         *
         * updateOrCreate() digunakan berdasarkan email:
         * - Jika email belum ada -> akun dibuat.
         * - Jika email sudah ada -> data akun diperbarui.
         *
         * Tidak membuat akun duplikat.
         */
        $accounts = [
            [
                'email' => 'admin@santovate.com',
                'name' => 'Santovate Admin',
                'password' => 'Santovate123!',
                'role' => 'admin',
                'config' => 'software-agency',
                'platform' => true,
            ],
            [
                'email' => 'sales@santovate.com',
                'name' => 'Santovate Sales',
                'password' => 'Sales123!',
                'role' => 'sales',
                'config' => 'software-agency',
                'platform' => false,
            ],
            [
                'email' => 'ahmadmazkur@santovate.com',
                'name' => 'Ahmad Mazkur',
                'password' => 'Mazkur!@#',
                'role' => 'sales',
                'config' => 'software-agency',
                'platform' => false,
            ],
            [
                'email' => 'randi@santovate.com',
                'name' => 'Randi',
                'password' => 'Randisantovate123!',
                'role' => 'sales',
                'config' => 'software-agency',
                'platform' => false,
            ],
            [
                'email' => 'demo.logistics@santovate.com',
                'name' => 'Demo Logistics Admin',
                'password' => 'DemoLogistics123!',
                'role' => 'admin',
                'config' => 'logistics-freight',
                'platform' => false,
            ],
            [
                'email' => 'demo.logistics.sales@santovate.com',
                'name' => 'Demo Logistics Sales',
                'password' => 'DemoLogisticsSales123!',
                'role' => 'sales',
                'config' => 'logistics-freight',
                'platform' => false,
            ],
            [
                'email' => 'demo.distributor@santovate.com',
                'name' => 'Demo Distributor Admin',
                'password' => 'DemoDistributor123!',
                'role' => 'admin',
                'config' => 'distributor-b2b',
                'platform' => false,
            ],
            [
                'email' => 'demo.distributor.sales@santovate.com',
                'name' => 'Demo Distributor Sales',
                'password' => 'DemoDistributorSales123!',
                'role' => 'sales',
                'config' => 'distributor-b2b',
                'platform' => false,
            ],
            [
                'email' => 'demo.parfum@santovate.com',
                'name' => 'Demo Parfum Admin',
                'password' => 'DemoParfum123!',
                'role' => 'admin',
                'config' => 'parfum-retail',
                'platform' => false,
            ],
            [
                'email' => 'demo.parfum.sales@santovate.com',
                'name' => 'Demo Parfum Sales',
                'password' => 'DemoParfumSales123!',
                'role' => 'sales',
                'config' => 'parfum-retail',
                'platform' => false,
            ],
        ];

        foreach ($accounts as $account) {
            $organization = $organizations[$account['config']];

            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'organization_id' => $organization->id,
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'is_active' => true,
                    'is_platform_admin' => (bool) ($account['platform'] ?? false),
                ]
            );
        }

        /*
         * Repair hanya untuk tenant reference yang NULL.
         *
         * Data organization yang sudah ada tidak diubah.
         */
        $softwareOrg = $organizations['software-agency'];

        User::whereNull('organization_id')
            ->update([
                'organization_id' => $softwareOrg->id,
            ]);

        DB::table('prospects')
            ->whereNull('organization_id')
            ->update([
                'organization_id' => $softwareOrg->id,
            ]);

        DB::table('import_batches')
            ->whereNull('organization_id')
            ->update([
                'organization_id' => $softwareOrg->id,
            ]);

        DB::table('sales_targets')
            ->whereNull('organization_id')
            ->update([
                'organization_id' => $softwareOrg->id,
            ]);

        /*
         * Remove invalid cross-workspace assignment links
         * yang dapat menyebabkan data prospect terlihat di
         * workspace yang salah.
         */
        User::query()
            ->select(['id', 'organization_id'])
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    DB::table('prospects')
                        ->where('assigned_to', $user->id)
                        ->whereNotNull('organization_id')
                        ->where('organization_id', '!=', $user->organization_id)
                        ->update([
                            'assigned_to' => null,
                        ]);
                }
            });
    }
}