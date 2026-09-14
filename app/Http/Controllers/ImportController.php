<?php
namespace App\Http\Controllers;
use App\Models\ImportBatch;
use App\Models\User;
use App\Notifications\ProspectsAssignedNotification;
use App\Services\ImportAssignmentService;
use App\Services\ProspectImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class ImportController extends Controller {
    public function index(Request $request): Response {$b=ImportBatch::where('organization_id',$request->user()->organization_id)->with('importer')->latest()->paginate(12);return Inertia::render('Imports/Index',['batches'=>$b]);}
    public function template(): BinaryFileResponse {return response()->download(storage_path('app/templates/santovate_prospect_import_template.xlsx'));}
    public function sample(): BinaryFileResponse {return response()->download(storage_path('app/templates/santovate_prospect_sample_30.xlsx'));}
    public function preview(Request $request,ProspectImportService $importer,ImportAssignmentService $assignments): Response|RedirectResponse {$request->validate(['file'=>['required','file','mimes:xlsx,xls,csv','max:5120']]);$dir=storage_path('app/imports/tmp');File::ensureDirectoryExists($dir);$token=(string)Str::uuid();$ext=strtolower($request->file('file')->getClientOriginalExtension());$path=$dir.'/'.$token.'.'.$ext;$request->file('file')->move($dir,basename($path));try{$rows=$importer->preview($path,$request->user()->organization_id);}catch(\Throwable$e){@unlink($path);return back()->withErrors(['file'=>$e instanceof RuntimeException?$e->getMessage():'File tidak dapat dibaca.']);}return Inertia::render('Imports/Preview',['rows'=>$rows,'token'=>$token,'extension'=>$ext,'originalName'=>$request->file('file')->getClientOriginalName(),'salesUsers'=>$assignments->activeSales($request->user()->organization_id)->map->only(['id','name','email'])->values(),'summary'=>['total'=>count($rows),'valid'=>collect($rows)->where('valid',true)->count(),'duplicates'=>collect($rows)->where('duplicate',true)->count(),'errors'=>collect($rows)->where('valid',false)->count()]]);}
    public function commit(Request $request,ProspectImportService $importer,ImportAssignmentService $assignments): RedirectResponse {$org=$request->user()->organization_id;$ids=$assignments->activeSales($org)->pluck('id')->all();$data=$request->validate(['token'=>['required','uuid'],'extension'=>['required','in:xlsx,xls,csv'],'original_name'=>['required','string','max:255'],'duplicate_mode'=>['required','in:skip,update'],'assignment_mode'=>['required',Rule::in(ImportAssignmentService::MODES)],'single_sales_id'=>['nullable','integer',Rule::in($ids)],'round_robin_sales_ids'=>['nullable','array'],'round_robin_sales_ids.*'=>['integer','distinct',Rule::in($ids)]]);$path=storage_path('app/imports/tmp/'.$data['token'].'.'.$data['extension']);abort_unless(is_file($path),404);try{[$batch,$summary]=DB::transaction(function()use($request,$data,$path,$importer,$org){$batch=ImportBatch::create(['organization_id'=>$org,'filename'=>$data['original_name'],'assignment_mode'=>$data['assignment_mode'],'imported_by'=>$request->user()->id]);$sum=$importer->import($path,$request->user(),$batch,$data['duplicate_mode'],['mode'=>$data['assignment_mode'],'single_sales_id'=>$data['single_sales_id']??null,'round_robin_sales_ids'=>$data['round_robin_sales_ids']??[]]);$batch->update(['total_rows'=>$sum['total'],'imported_rows'=>$sum['imported'],'updated_rows'=>$sum['updated'],'skipped_rows'=>$sum['skipped'],'error_rows'=>count($sum['errors']),'errors'=>$sum['errors']?:null,'assignment_summary'=>['unassigned'=>$sum['unassigned'],'assigned_total'=>collect($sum['assignments'])->sum('count')]]);return[$batch,$sum];});}catch(\Throwable$e){report($e);return back()->withErrors(['import'=>$e->getMessage()]);}finally{@unlink($path);}foreach($summary['assignments']as$userId=>$a){$sales=User::where('organization_id',$org)->where('role','sales')->where('is_active',true)->find((int)$userId);if($sales&&$a['count']>0)$sales->notify(new ProspectsAssignedNotification($batch,(int)$a['count']));}return redirect()->route('imports.show',$batch)->with('success','Import selesai.');}
    public function show(Request$request,ImportBatch$batch):Response{abort_unless($batch->organization_id===$request->user()->organization_id,403);$batch->load('importer');return Inertia::render('Imports/Show',['batch'=>$batch]);}
}
