<?php
namespace App\Http\Controllers;
use App\Jobs\SendWhatsAppCampaignRecipient;
use App\Models\FollowUpTemplate;
use App\Models\Prospect;
use App\Models\WhatsAppCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class CampaignController extends Controller {
    public function index(Request $request): Response {
        $org=$request->user()->organization_id;
        return Inertia::render('Campaigns/Index',[
            'campaigns'=>WhatsAppCampaign::where('organization_id',$org)->with('template')->latest()->get(),
            'templates'=>FollowUpTemplate::where('organization_id',$org)->where('is_active',true)->whereNotNull('meta_template_name')->get(),
            'eligibleCount'=>Prospect::visibleTo($request->user())->whereNotNull('phone_normalized')->whereNotNull('whatsapp_opt_in_at')->whereNull('whatsapp_opt_out_at')->count(),
        ]);
    }
    public function store(Request $request): RedirectResponse {
        $data=$request->validate(['name'=>['required','string','max:180'],'template_id'=>['required','integer']]);
        $template=FollowUpTemplate::where('organization_id',$request->user()->organization_id)->where('meta_status','approved')->findOrFail($data['template_id']);
        $campaign=WhatsAppCampaign::create(['organization_id'=>$request->user()->organization_id,'follow_up_template_id'=>$template->id,'created_by'=>$request->user()->id,'name'=>$data['name'],'status'=>'draft']);
        $prospects=Prospect::visibleTo($request->user())->whereNotNull('phone_normalized')->whereNotNull('whatsapp_opt_in_at')->whereNull('whatsapp_opt_out_at')->pluck('id');
        foreach ($prospects as $id) $campaign->recipients()->create(['prospect_id'=>$id,'status'=>'queued']);
        $campaign->update(['total_recipients'=>$prospects->count()]);
        return back()->with('success','Campaign dibuat dengan '.$prospects->count().' penerima opt-in.');
    }
    public function send(Request $request, WhatsAppCampaign $campaign): RedirectResponse {
        abort_unless($campaign->organization_id===$request->user()->organization_id,403);
        abort_if($campaign->status==='running',422,'Campaign sudah berjalan.');
        $campaign->update(['status'=>'running','started_at'=>now()]);
        foreach ($campaign->recipients()->where('status','queued')->pluck('id') as $id) SendWhatsAppCampaignRecipient::dispatch($id);
        if ($campaign->total_recipients===0) $campaign->update(['status'=>'finished','finished_at'=>now()]);
        return back()->with('success','Campaign masuk queue. Jalankan queue worker untuk memproses pengiriman.');
    }
}
