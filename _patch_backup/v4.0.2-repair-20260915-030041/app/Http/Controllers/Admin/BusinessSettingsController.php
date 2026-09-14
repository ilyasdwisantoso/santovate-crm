<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\BusinessConfiguration;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class BusinessSettingsController extends Controller {
    public function index(Request $request): Response {
        $org=$request->user()->organization->load('businessConfiguration');
        return Inertia::render('Settings/Business',['organization'=>$org,'activeSubscription'=>$org->activeSubscription(),'configurations'=>BusinessConfiguration::where('is_active',true)->orderBy('sort_order')->get(),'plans'=>SubscriptionPlan::where('is_active',true)->orderBy('sort_order')->get()]);
    }
}
