<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessConfiguration;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ClientOnboardingController extends Controller
{
    public function index(): Response
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id','key','name','monthly_price','annual_price','user_limit','prospect_limit']);

        $configurations = BusinessConfiguration::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id','key','name','industry','monthly_addon_price','annual_addon_price']);

        $clients = Organization::query()
            ->with(['businessConfiguration:id,key,name'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (Organization $organization) {
                $owner = $organization->users()->where('role', 'admin')->oldest('id')->first(['id','name','email','phone']);
                $subscription = $organization->activeSubscription();
                return [
                    'id'=>$organization->id,
                    'name'=>$organization->name,
                    'slug'=>$organization->slug,
                    'status'=>$organization->status,
                    'is_demo'=>(bool)data_get($organization->settings, 'demo', false),
                    'owner'=>$owner,
                    'configuration'=>$organization->businessConfiguration?->only(['id','key','name']),
                    'subscription'=>$subscription ? [
                        'id'=>$subscription->id,
                        'status'=>$subscription->status,
                        'billing_cycle'=>$subscription->billing_cycle,
                        'total_amount'=>(int)$subscription->total_amount,
                        'starts_at'=>$subscription->starts_at?->toIso8601String(),
                        'ends_at'=>$subscription->ends_at?->toIso8601String(),
                        'plan'=>$subscription->plan?->only(['id','key','name','user_limit']),
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Admin/Clients/Index', [
            'plans'=>$plans,
            'configurations'=>$configurations,
            'clients'=>$clients,
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'business_name'=>['required','string','max:180'],
            'owner_name'=>['required','string','max:180'],
            'owner_email'=>['required','email','max:255','unique:users,email'],
            'owner_phone'=>['nullable','string','max:50'],
            'password'=>['required','confirmed',Password::min(8)],
            'plan_key'=>['required','exists:subscription_plans,key'],
            'configuration_key'=>['required','exists:business_configurations,key'],
            'billing_cycle'=>['required','in:monthly,annual'],
            'payment_reference'=>['nullable','string','max:120','unique:payments,reference_id'],
        ]);

        DB::transaction(function () use ($data, $request, $subscriptions) {
            $plan = SubscriptionPlan::query()->where('key', $data['plan_key'])->where('is_active', true)->firstOrFail();
            $configuration = BusinessConfiguration::query()->where('key', $data['configuration_key'])->where('is_active', true)->firstOrFail();

            $slugBase = Str::slug($data['business_name']) ?: 'client';
            do {
                $slug = $slugBase.'-'.Str::lower(Str::random(5));
            } while (Organization::query()->where('slug', $slug)->exists());

            $organization = Organization::create([
                'name'=>$data['business_name'],
                'slug'=>$slug,
                'business_configuration_id'=>null,
                'status'=>'pending',
                'contact_email'=>$data['owner_email'],
                'contact_phone'=>$data['owner_phone'] ?: null,
                'settings'=>[
                    'demo'=>false,
                    'onboarding_source'=>'manual_paid',
                    'created_by_platform_admin'=>$request->user()->id,
                ],
            ]);

            User::create([
                'organization_id'=>$organization->id,
                'name'=>$data['owner_name'],
                'email'=>$data['owner_email'],
                'phone'=>$data['owner_phone'] ?: null,
                'password'=>$data['password'],
                'role'=>'admin',
                'is_active'=>true,
                'is_platform_admin'=>false,
            ]);

            $baseAmount = $data['billing_cycle'] === 'annual' ? $plan->annual_price : $plan->monthly_price;
            $configurationAmount = $data['billing_cycle'] === 'annual' ? $configuration->annual_addon_price : $configuration->monthly_addon_price;

            $subscription = Subscription::create([
                'organization_id'=>$organization->id,
                'subscription_plan_id'=>$plan->id,
                'business_configuration_id'=>$configuration->id,
                'billing_cycle'=>$data['billing_cycle'],
                'status'=>'pending',
                'base_amount'=>$baseAmount,
                'configuration_amount'=>$configurationAmount,
                'total_amount'=>$baseAmount + $configurationAmount,
            ]);

            $reference = filled($data['payment_reference'] ?? null)
                ? $data['payment_reference']
                : 'MANUAL-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));

            Payment::create([
                'organization_id'=>$organization->id,
                'subscription_id'=>$subscription->id,
                'provider'=>'manual',
                'reference_id'=>$reference,
                'status'=>'paid',
                'amount'=>$subscription->total_amount,
                'payment_method'=>'manual',
                'payment_channel'=>'manual',
                'paid_at'=>now(),
                'provider_payload'=>[
                    'source'=>'platform_admin',
                    'confirmed_by'=>$request->user()->id,
                ],
            ]);

            $subscriptions->activate($subscription);
        });

        return back()->with('success', 'Client berhasil dibuat dan subscription langsung aktif. Kirim email serta password yang Anda tentukan kepada client.');
    }
}
