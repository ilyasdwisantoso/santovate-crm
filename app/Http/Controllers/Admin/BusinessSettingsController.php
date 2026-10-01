<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessConfiguration;
use App\Models\SubscriptionPlan;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements): Response
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 409, 'Akun belum terhubung ke workspace.');
        $organization->load('businessConfiguration');

        return Inertia::render('Settings/Business', [
            'organization'=>$organization,
            'activeSubscription'=>$organization->activeSubscription(),
            'entitlements'=>$entitlements->snapshot($organization),
            'configurations'=>BusinessConfiguration::where('is_active', true)->orderBy('sort_order')->get(),
            'plans'=>SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
