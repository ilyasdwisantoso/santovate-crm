<?php
namespace App\Http\Controllers;
use App\Models\BusinessConfiguration;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
class RegistrationController extends Controller {
    public function create(Request $request): Response {
        return Inertia::render('Public/Register',['plans'=>SubscriptionPlan::where('is_active',true)->orderBy('sort_order')->get(),'configurations'=>BusinessConfiguration::where('is_active',true)->orderBy('sort_order')->get(),'selected'=>['plan'=>$request->query('plan'),'config'=>$request->query('config'),'billing'=>$request->query('billing','monthly')]]);
    }
    public function store(Request $request): RedirectResponse {
        $data=$request->validate([
            'business_name'=>['required','string','max:180'],'name'=>['required','string','max:180'],'email'=>['required','email','max:255','unique:users,email'],
            'phone'=>['required','string','max:50'],'password'=>['required','confirmed',Password::min(8)],
            'plan_key'=>['required','exists:subscription_plans,key'],'configuration_key'=>['required','exists:business_configurations,key'],'billing_cycle'=>['required','in:monthly,annual'],
        ]);
        [$user,$subscription]=DB::transaction(function() use($data){
            $plan=SubscriptionPlan::where('key',$data['plan_key'])->where('is_active',true)->firstOrFail();
            $config=BusinessConfiguration::where('key',$data['configuration_key'])->where('is_active',true)->firstOrFail();
            $slug=Str::slug($data['business_name']).'-'.Str::lower(Str::random(5));
            $org=Organization::create(['name'=>$data['business_name'],'slug'=>$slug,'business_configuration_id'=>null,'status'=>'pending','contact_email'=>$data['email'],'contact_phone'=>$data['phone']]);
            $user=User::create(['organization_id'=>$org->id,'name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone'],'password'=>$data['password'],'role'=>'admin','is_active'=>true]);
            $base=$data['billing_cycle']==='annual'?$plan->annual_price:$plan->monthly_price;
            $addon=$data['billing_cycle']==='annual'?$config->annual_addon_price:$config->monthly_addon_price;
            $sub=Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'business_configuration_id'=>$config->id,'billing_cycle'=>$data['billing_cycle'],'status'=>'pending','base_amount'=>$base,'configuration_amount'=>$addon,'total_amount'=>$base+$addon]);
            return [$user,$sub];
        });
        Auth::login($user);
        return redirect()->route('subscription.checkout')->with('success','Workspace dibuat. Selesaikan pembayaran untuk mengaktifkan konfigurasi CRM.');
    }
}
