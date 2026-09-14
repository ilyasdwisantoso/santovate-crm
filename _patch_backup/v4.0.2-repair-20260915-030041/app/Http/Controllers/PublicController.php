<?php
namespace App\Http\Controllers;
use App\Models\BusinessConfiguration;
use App\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;
class PublicController extends Controller {
    public function plans(): Response { return Inertia::render('Public/Plans',['plans'=>SubscriptionPlan::where('is_active',true)->orderBy('sort_order')->get()]); }
    public function configurations(): Response { return Inertia::render('Public/BusinessConfigurations',['configurations'=>BusinessConfiguration::where('is_active',true)->orderBy('sort_order')->get()]); }
    public function legal(string $page): Response {
        abort_unless(in_array($page,['terms','refund-policy','privacy-policy','faq','contact'],true),404);
        return Inertia::render('Public/Legal',['page'=>$page,'support'=>config('santovate.support')]);
    }
}
