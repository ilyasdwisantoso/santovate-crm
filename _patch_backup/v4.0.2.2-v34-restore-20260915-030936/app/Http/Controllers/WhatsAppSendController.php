<?php
namespace App\Http\Controllers;
use App\Models\FollowUpTemplate;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class WhatsAppSendController extends Controller {
    public function store(Request $request, Prospect $prospect, WhatsAppService $whatsapp): RedirectResponse {
        abort_unless(Prospect::query()->visibleTo($request->user())->whereKey($prospect->id)->exists(),403);
        $data=$request->validate(['template_id'=>['required','integer'],'message'=>['nullable','string','max:5000'],'queue_type'=>['nullable',Rule::in(['response_needed','lead_age_due','no_reply','scheduled'])],'feedback_note'=>['nullable','string','max:2000'],'next_follow_up_days'=>['nullable','integer','min:0','max:60']]);
        if (($data['queue_type'] ?? null)==='lead_age_due' && blank($data['feedback_note'] ?? null)) return back()->withErrors(['feedback_note'=>'Feedback wajib diisi untuk lead H-3.']);
        $template=FollowUpTemplate::where('organization_id',$request->user()->organization_id)->findOrFail($data['template_id']);
        try { $message=$whatsapp->send($prospect,$request->user(),$template,$data['message'] ?? null); }
        catch (\Throwable $e) { report($e); return back()->with('error',$e->getMessage()); }
        $days=(int)($data['next_follow_up_days'] ?? 5); $prospect->update(['next_follow_up_at'=>$days>0?now()->addDays($days):null]);
        if (($data['queue_type'] ?? null)==='lead_age_due') {
            $prospect->update(['last_feedback_at'=>now(),'last_feedback_status'=>'follow_up_sent','last_feedback_note'=>$data['feedback_note']]);
            ProspectActivity::create(['prospect_id'=>$prospect->id,'user_id'=>$request->user()->id,'type'=>'follow_up_feedback','title'=>'Feedback wajib H-3','description'=>$data['feedback_note'],'occurred_at'=>now()]);
        }
        ProspectActivity::create(['prospect_id'=>$prospect->id,'user_id'=>$request->user()->id,'type'=>'whatsapp','title'=>'WhatsApp dikirim via Cloud API','description'=>$message->body,'occurred_at'=>now()]);
        return back()->with('success','Pesan dikirim melalui WhatsApp Cloud API.');
    }
}
