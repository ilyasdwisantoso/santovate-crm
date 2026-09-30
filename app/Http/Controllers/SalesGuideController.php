<?php

namespace App\Http\Controllers;

use App\Models\SolutionCatalogItem;
use App\Services\SalesGuideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesGuideController extends Controller
{
    public function index(Request $request, SalesGuideService $guide): Response
    {
        return Inertia::render('SalesGuide/Index', $guide->forUser($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $this->validated($request);
        SolutionCatalogItem::create(['organization_id'=>$request->user()->organization_id] + $data);
        return back()->with('success', 'Solution pricebook berhasil ditambahkan.');
    }

    public function update(Request $request, SolutionCatalogItem $solution): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless((int)$solution->organization_id === (int)$request->user()->organization_id, 403);
        $solution->update($this->validated($request, $solution));
        return back()->with('success', 'Solution pricebook diperbarui.');
    }

    private function validated(Request $request, ?SolutionCatalogItem $solution = null): array
    {
        $org = $request->user()->organization_id;
        $data = $request->validate([
            'code'=>['required','string','max:80',Rule::unique('solution_catalog_items','code')->where(fn($q)=>$q->where('organization_id',$org))->ignore($solution?->id)],
            'category'=>['required','string','max:120'],
            'name'=>['required','string','max:180'],
            'description'=>['nullable','string','max:5000'],
            'pricing_model'=>['required',Rule::in(['one_time','recurring','custom'])],
            'unit'=>['required','string','max:50'],
            'internal_cost'=>['nullable','numeric','min:0'],
            'recommended_price'=>['nullable','numeric','min:0'],
            'minimum_price'=>['nullable','numeric','min:0'],
            'dependencies_text'=>['nullable','string','max:3000'],
            'upsell_notes'=>['nullable','string','max:5000'],
            'sales_notes'=>['nullable','string','max:5000'],
            'is_active'=>['nullable','boolean'],
            'sort_order'=>['nullable','integer','min:0','max:10000'],
        ]);
        $dependencies = collect(preg_split('/[,\n]+/', (string)($data['dependencies_text'] ?? '')))
            ->map(fn($x)=>trim($x))->filter()->values()->all();
        unset($data['dependencies_text']);
        $data['dependencies'] = $dependencies;
        $data['internal_cost'] = $data['internal_cost'] ?? 0;
        $data['recommended_price'] = $data['recommended_price'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        return $data;
    }
}
