<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_addons')) {
            Schema::create('subscription_addons', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('resource_key', 60);
                $table->unsignedInteger('resource_quantity');
                $table->unsignedBigInteger('monthly_price')->default(0);
                $table->unsignedBigInteger('annual_price')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['resource_key','is_active']);
            });
        }

        if (!Schema::hasTable('subscription_addon_orders')) {
            Schema::create('subscription_addon_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_addon_id')->constrained('subscription_addons')->restrictOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('billing_cycle', 20);
                $table->unsignedInteger('units')->default(1);
                $table->string('resource_key', 60);
                $table->unsignedBigInteger('resource_quantity');
                $table->unsignedBigInteger('unit_price');
                $table->unsignedBigInteger('total_amount');
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['organization_id','status']);
                $table->index(['subscription_id','status']);
            });
        }

        if (!Schema::hasTable('subscription_addon_payments')) {
            Schema::create('subscription_addon_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_addon_order_id')->constrained('subscription_addon_orders')->cascadeOnDelete();
                $table->string('provider', 40)->default('ipaymu');
                $table->string('reference_id', 160)->unique();
                $table->string('provider_transaction_id')->nullable()->index();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedBigInteger('amount');
                $table->string('payment_method', 40)->nullable();
                $table->string('payment_channel', 60)->nullable();
                $table->text('checkout_url')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->json('provider_payload')->nullable();
                $table->timestamps();
                $table->index(['organization_id','status']);
            });
        }

        $now = now();
        $catalog = [
            [
                'key'=>'extra-user-1',
                'name'=>'Extra User +1',
                'description'=>'Tambahkan 1 user aktif ke kapasitas workspace sampai akhir subscription aktif.',
                'resource_key'=>'users','resource_quantity'=>1,
                'monthly_price'=>150000,'annual_price'=>1500000,'sort_order'=>10,
            ],
            [
                'key'=>'extra-user-5',
                'name'=>'Team Pack +5 Users',
                'description'=>'Tambahkan 5 user aktif sekaligus untuk tim yang berkembang.',
                'resource_key'=>'users','resource_quantity'=>5,
                'monthly_price'=>600000,'annual_price'=>6000000,'sort_order'=>20,
            ],
            [
                'key'=>'prospect-pack-5000',
                'name'=>'+5.000 Prospects',
                'description'=>'Tambahkan kapasitas 5.000 prospect ke workspace aktif.',
                'resource_key'=>'prospects','resource_quantity'=>5000,
                'monthly_price'=>100000,'annual_price'=>1000000,'sort_order'=>30,
            ],
        ];

        foreach ($catalog as $item) {
            DB::table('subscription_addons')->updateOrInsert(
                ['key'=>$item['key']],
                $item + ['is_active'=>true,'metadata'=>json_encode(['seed'=>'batch5c'], JSON_UNESCAPED_SLASHES),'created_at'=>$now,'updated_at'=>$now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addon_payments');
        Schema::dropIfExists('subscription_addon_orders');
        Schema::dropIfExists('subscription_addons');
    }
};
