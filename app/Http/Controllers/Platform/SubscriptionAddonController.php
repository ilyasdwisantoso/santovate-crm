<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAddonOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionAddonController extends Controller
{
    public function index(): Response
    {
        $addons = SubscriptionAddon::query()->orderBy('sort_order')->orderBy('id')->get()->map(function(SubscriptionAddon $addon){
            $activated = SubscriptionAddonOrder::query()->where('subscription_addon_id',$addon->id)->where('status','activated');
            return [
                'id'=>$addon->id,'key'=>$addon->key,'name'=>$addon->name,'description'=>$addon->description,
                'resource_key'=>$addon->resource_key,'resource_quantity'=>(int)$addon->resource_quantity,
                'monthly_price'=>(int)$addon->monthly_price,'annual_price'=>(int)$addon->annual_price,
                'is_active'=>(bool)$addon->is_active,'sort_order'=>(int)$addon->sort_order,
                'activated_orders'=>(clone $activated)->count(),
                'active_resource_quantity'=>(int)(clone $activated)->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))->sum('resource_quantity'),
            ];
        })->values();

        $recent = SubscriptionAddonOrder::query()->with(['organization:id,name','addon:id,name','payment:id,subscription_addon_order_id,status,reference_id,paid_at'])
            ->latest('id')->limit(15)->get()->map(fn($order)=>[
                'id'=>$order->id,'organization'=>$order->organization?->name,'addon'=>$order->addon?->name,
                'units'=>(int)$order->units,'resource_quantity'=>(int)$order->resource_quantity,
                'resource_key'=>$order->resource_key,'total_amount'=>(int)$order->total_amount,'status'=>$order->status,
                'payment_status'=>$order->payment?->status,'reference'=>$order->payment?->reference_id,
                'created_at'=>$order->created_at?->toIso8601String(),
            ])->values();

        return Inertia::render('Platform/Addons/Index',['addons'=>$addons,'recentOrders'=>$recent]);
    }

    public function update(Request $request, SubscriptionAddon $addon): RedirectResponse
    {
        $data = $request->validate([
            'name'=>['required','string','max:160'],
            'description'=>['nullable','string','max:1000'],
            'monthly_price'=>['required','integer','min:1000'],
            'annual_price'=>['required','integer','min:10000'],
            'is_active'=>['required','boolean'],
            'sort_order'=>['required','integer','min:0','max:9999'],
        ]);

        $before = $addon->only(['name','description','monthly_price','annual_price','is_active','sort_order']);
        $addon->update($data);

        PlatformAuditLog::create([
            'actor_id'=>$request->user()->id,
            'action'=>'subscription_addon.updated',
            'target_type'=>SubscriptionAddon::class,
            'target_id'=>$addon->id,
            'metadata'=>[
                'key'=>$addon->key,
                'resource_key'=>$addon->resource_key,
                'resource_quantity'=>$addon->resource_quantity,
                'before'=>$before,
                'after'=>$addon->fresh()->only(array_keys($before)),
            ],
        ]);

        return back()->with('success','Subscription add-on diperbarui. Perubahan harga hanya berlaku untuk order baru.');
    }
}
