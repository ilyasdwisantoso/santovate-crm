<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
class ProductController extends Controller {
    public function index(Request $request): Response { return Inertia::render('Products/Index',['products'=>Product::where('organization_id',$request->user()->organization_id)->orderByDesc('is_active')->orderBy('name')->get()]); }
    public function store(Request $request): RedirectResponse { $org=$request->user()->organization_id; $data=$request->validate($this->rules($org)); Product::create(['organization_id'=>$org]+$data+['is_active'=>$request->boolean('is_active',true)]); return back()->with('success','Produk ditambahkan.'); }
    public function update(Request $request, Product $product): RedirectResponse { abort_unless($product->organization_id===$request->user()->organization_id,403); $data=$request->validate($this->rules($product->organization_id,$product->id)); $product->update($data+['is_active'=>$request->boolean('is_active')]); return back()->with('success','Produk diperbarui.'); }
    public function destroy(Request $request, Product $product): RedirectResponse { abort_unless($product->organization_id===$request->user()->organization_id,403); $product->delete(); return back()->with('success','Produk dihapus.'); }
    private function rules(int $org,?int $ignore=null): array { return ['sku'=>['required','string','max:100',Rule::unique('products','sku')->where(fn($q)=>$q->where('organization_id',$org))->ignore($ignore)],'name'=>['required','string','max:180'],'variant'=>['nullable','string','max:180'],'price'=>['required','integer','min:0'],'image_url'=>['nullable','url','max:2000'],'description'=>['nullable','string','max:5000'],'is_active'=>['nullable','boolean']]; }
}
