<?php
namespace App\Http\Controllers;
use App\Models\FollowUpTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class FollowUpTemplateController extends Controller {
    public function update(Request $request, FollowUpTemplate $template): RedirectResponse {
        abort_unless($request->user()?->isAdmin() && $template->organization_id===$request->user()->organization_id,403);
        $data=$request->validate([
            'name'=>['required','string','max:120'],'wait_days'=>['required','integer','min:0','max:60'],'message'=>['required','string','max:5000'],
            'header_type'=>['nullable','in:none,image'],'image_url'=>['nullable','url','max:2000'],'meta_template_name'=>['nullable','string','max:255'],
            'meta_language'=>['nullable','string','max:20'],'meta_status'=>['nullable','in:draft,pending,approved,rejected'],'meta_category'=>['nullable','in:marketing,utility,authentication'],
            'body_parameters'=>['nullable','array'],'body_parameters.*'=>['string','max:80'],
        ]);
        $template->update($data+['created_by'=>$request->user()->id]);
        return back()->with('success','Template follow-up berhasil diperbarui.');
    }
}
