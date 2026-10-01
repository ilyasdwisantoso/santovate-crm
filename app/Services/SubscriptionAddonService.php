<?php

namespace App\Services;

use App\Models\EntitlementGrant;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAddonOrder;
use App\Models\SubscriptionAddonPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubscriptionAddonService
{
    public function activeSubscription(Organization $organization): Subscription
    {
        $subscription = $organization->activeSubscription();
        if (!$subscription) {
            throw ValidationException::withMessages(['subscription'=>'Workspace belum memiliki subscription aktif.']);
        }
        return $subscription;
    }

    public function assertPurchasable(Organization $organization): void
    {
        $internal = $organization->slug === 'santovate-internal' || (bool)data_get($organization->settings,'internal',false);
        if ($internal) {
            throw ValidationException::withMessages(['addon'=>'Add-on berbayar tidak berlaku untuk workspace internal Santovate.']);
        }
    }

    public function createOrder(User $user, SubscriptionAddon $addon, int $units): SubscriptionAddonOrder
    {
        $organization = $user->organization;
        if (!$organization) {
            throw ValidationException::withMessages(['organization'=>'User belum terhubung ke workspace.']);
        }

        $this->assertPurchasable($organization);
        if (!$addon->is_active) {
            throw ValidationException::withMessages(['addon'=>'Add-on ini sedang tidak tersedia.']);
        }

        $maxUnits = max(1,(int)config('subscription_addons.max_units_per_order',20));
        if ($units < 1 || $units > $maxUnits) {
            throw ValidationException::withMessages(['units'=>"Jumlah paket harus antara 1 sampai {$maxUnits}."]);
        }

        return DB::transaction(function () use ($user,$organization,$addon,$units) {
            $active = $this->activeSubscription($organization);
            $subscription = Subscription::query()->whereKey($active->id)->lockForUpdate()->firstOrFail();
            if ($subscription->status !== 'active' || ($subscription->ends_at && $subscription->ends_at->lte(now()))) {
                throw ValidationException::withMessages(['subscription'=>'Subscription sudah tidak aktif.']);
            }

            $unitPrice = $addon->priceFor($subscription->billing_cycle);
            if ($unitPrice <= 0) {
                throw ValidationException::withMessages(['addon'=>'Harga add-on untuk billing cycle ini belum dikonfigurasi.']);
            }

            $resourceQuantity = (int)$addon->resource_quantity * $units;
            $total = $unitPrice * $units;

            return SubscriptionAddonOrder::create([
                'organization_id'=>$organization->id,
                'subscription_id'=>$subscription->id,
                'subscription_addon_id'=>$addon->id,
                'requested_by'=>$user->id,
                'billing_cycle'=>$subscription->billing_cycle,
                'units'=>$units,
                'resource_key'=>$addon->resource_key,
                'resource_quantity'=>$resourceQuantity,
                'unit_price'=>$unitPrice,
                'total_amount'=>$total,
                'status'=>'pending',
                'ends_at'=>$subscription->ends_at,
                'metadata'=>[
                    'addon_key'=>$addon->key,
                    'addon_name'=>$addon->name,
                    'plan_key'=>$subscription->plan?->key,
                    'pricing_mode'=>'full_cycle_no_proration',
                ],
            ]);
        });
    }

    public function activate(SubscriptionAddonPayment $payment): SubscriptionAddonOrder
    {
        return DB::transaction(function () use ($payment) {
            $lockedPayment = SubscriptionAddonPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = SubscriptionAddonOrder::query()->whereKey($lockedPayment->subscription_addon_order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'activated') return $order;
            if ($lockedPayment->status !== 'paid') {
                throw new RuntimeException('Add-on payment belum berstatus paid.');
            }
            if ((int)$lockedPayment->amount !== (int)$order->total_amount) {
                $order->update(['status'=>'pending_verification']);
                throw new RuntimeException('Nominal payment add-on tidak sesuai order.');
            }

            $subscription = Subscription::query()->whereKey($order->subscription_id)->lockForUpdate()->firstOrFail();
            $active = $subscription->status === 'active' && (!$subscription->ends_at || $subscription->ends_at->gt(now()));
            if (!$active) {
                $order->update(['status'=>'pending_verification']);
                throw new RuntimeException('Payment diterima setelah subscription tidak aktif; perlu review manual.');
            }

            $startedAt = now();
            $endsAt = $subscription->ends_at;

            EntitlementGrant::query()->updateOrCreate(
                [
                    'source_type'=>SubscriptionAddonOrder::class,
                    'source_id'=>$order->id,
                    'key'=>$order->resource_key,
                ],
                [
                    'organization_id'=>$order->organization_id,
                    'subscription_id'=>$subscription->id,
                    'kind'=>'limit',
                    'operation'=>'add',
                    'integer_value'=>$order->resource_quantity,
                    'boolean_value'=>null,
                    'reference'=>'ADDON-'.$order->id,
                    'status'=>'active',
                    'starts_at'=>$startedAt,
                    'ends_at'=>$endsAt,
                    'metadata'=>[
                        'subscription_addon_order_id'=>$order->id,
                        'subscription_addon_id'=>$order->subscription_addon_id,
                        'billing_cycle'=>$order->billing_cycle,
                        'units'=>$order->units,
                        'unit_price'=>$order->unit_price,
                        'total_amount'=>$order->total_amount,
                        'payment_reference'=>$lockedPayment->reference_id,
                    ],
                    'created_by'=>$order->requested_by,
                ]
            );

            $order->update([
                'status'=>'activated',
                'starts_at'=>$startedAt,
                'ends_at'=>$endsAt,
                'activated_at'=>$startedAt,
            ]);

            return $order->fresh(['addon','payment']);
        });
    }
}
