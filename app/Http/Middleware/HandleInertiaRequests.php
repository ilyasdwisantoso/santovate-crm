<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;
class HandleInertiaRequests extends Middleware {
    protected $rootView='app';
    public function share(Request $request): array {
        $user=$request->user();$notifications=[];$unread=0;$organization=null;$subscription=null;
        if($user){$unread=$user->unreadNotifications()->count();$notifications=$user->notifications()->latest()->limit(8)->get()->map(fn($n)=>['id'=>$n->id,'data'=>$n->data,'read_at'=>$n->read_at?->toIso8601String(),'created_at'=>$n->created_at?->toIso8601String()])->values()->all();$org=$user->organization?->load('businessConfiguration');if($org){$organization=['id'=>$org->id,'name'=>$org->name,'slug'=>$org->slug,'status'=>$org->status,'business_configuration'=>$org->businessConfiguration?->only(['id','key','name','industry'])];$active=$org->activeSubscription();if($active)$subscription=['id'=>$active->id,'status'=>$active->status,'billing_cycle'=>$active->billing_cycle,'ends_at'=>$active->ends_at?->toIso8601String(),'plan'=>$active->plan?->only(['id','key','name','user_limit','prospect_limit','features'])];}}
        return [...parent::share($request),'auth'=>['user'=>$user?['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,'job_title'=>$user->job_title,'department'=>$user->department,'profile_initials'=>$user->profile_initials,'whatsapp_signature'=>$user->whatsapp_signature,'role'=>$user->role,'is_admin'=>$user->isAdmin()]:null],'organization'=>$organization,'subscription'=>$subscription,'notifications'=>['unread_count'=>$unread,'items'=>$notifications],'flash'=>['success'=>fn()=>$request->session()->get('success'),'error'=>fn()=>$request->session()->get('error')]];
    }
}
