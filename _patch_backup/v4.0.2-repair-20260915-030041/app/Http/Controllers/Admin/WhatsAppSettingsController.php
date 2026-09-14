<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class WhatsAppSettingsController extends Controller {
    public function edit(Request $request): Response {
        $channel=WhatsAppChannel::firstOrNew(['organization_id'=>$request->user()->organization_id]);
        return Inertia::render('Settings/WhatsApp',['channel'=>$channel->only(['provider','phone_number_id','waba_id','is_active','verified_at']),'hasToken'=>filled($channel->access_token),'webhookUrl'=>route('api.whatsapp.receive')]);
    }
    public function update(Request $request): RedirectResponse {
        $data=$request->validate(['phone_number_id'=>['nullable','string','max:100'],'waba_id'=>['nullable','string','max:100'],'access_token'=>['nullable','string','max:5000'],'is_active'=>['nullable','boolean']]);
        $channel=WhatsAppChannel::firstOrNew(['organization_id'=>$request->user()->organization_id]);
        $channel->provider='meta_cloud'; $channel->phone_number_id=$data['phone_number_id'] ?? null; $channel->waba_id=$data['waba_id'] ?? null; $channel->is_active=$request->boolean('is_active');
        if (filled($data['access_token'] ?? null)) $channel->access_token=$data['access_token'];
        $channel->save();
        return back()->with('success','Konfigurasi WhatsApp Cloud API disimpan.');
    }
}
