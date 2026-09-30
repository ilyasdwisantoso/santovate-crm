<?php

namespace App\Services;

use App\Models\BusinessConfiguration;
use App\Models\SolutionCatalogItem;
use App\Models\SubscriptionPlan;
use App\Models\User;

class SalesGuideService
{
    public function forUser(User $user): array
    {
        return [
            'plans'=>$this->plans(),
            'configurations'=>$this->configurations(),
            'solutions'=>$this->solutions($user),
            'concepts'=>array_values(config('sales-guide.concepts', [])),
            'sales_flow'=>array_values(config('sales-guide.sales_flow', [])),
            'last_reviewed'=>config('sales-guide.last_reviewed'),
            'can_manage_catalog'=>$user->isAdmin(),
        ];
    }

    public function plans(): array
    {
        return SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn ($plan) => [
            'id'=>$plan->id,'key'=>$plan->key,'name'=>$plan->name,'description'=>$plan->description,
            'monthly_price'=>(int)$plan->monthly_price,'annual_price'=>(int)$plan->annual_price,
            'user_limit'=>(int)$plan->user_limit,'prospect_limit'=>(int)$plan->prospect_limit,
            'features'=>$plan->features ?: [],
        ])->values()->all();
    }

    public function configurations(): array
    {
        return BusinessConfiguration::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn ($config) => [
            'id'=>$config->id,'key'=>$config->key,'name'=>$config->name,'industry'=>$config->industry,
            'description'=>$config->description,'monthly_price'=>(int)$config->monthly_addon_price,
            'annual_price'=>(int)$config->annual_addon_price,'pipeline'=>$config->pipeline ?: [],
        ])->values()->all();
    }

    public function solutions(User $user, bool $activeOnly = false): array
    {
        $this->ensureDefaults($user);
        $query = SolutionCatalogItem::query()->visibleTo($user)->orderBy('category')->orderBy('sort_order')->orderBy('name');
        if ($activeOnly) $query->where('is_active', true);

        return $query->get()->map(fn (SolutionCatalogItem $item) => $this->solutionPayload($item))->values()->all();
    }

    private function ensureDefaults(User $user): void
    {
        foreach (array_values(config('sales-guide.solution_templates', [])) as $index => $template) {
            SolutionCatalogItem::query()->firstOrCreate(
                ['organization_id'=>$user->organization_id,'code'=>$template['code']],
                [
                    'category'=>$template['category'],'name'=>$template['name'],'description'=>$template['description'],
                    'pricing_model'=>'custom','unit'=>'project','internal_cost'=>0,'recommended_price'=>0,'minimum_price'=>null,
                    'dependencies'=>$template['dependencies'] ?? [],'upsell_notes'=>$template['upsell_notes'] ?? null,
                    'sales_notes'=>'Isi internal cost, recommended selling price, dan minimum selling price sebelum dipakai sebagai price reference.',
                    'is_active'=>true,'sort_order'=>$index+1,
                ]
            );
        }
    }

    public function solutionPayload(SolutionCatalogItem $item): array
    {
        $cost = (float)$item->internal_cost;
        $sell = (float)$item->recommended_price;
        return [
            'id'=>$item->id,'code'=>$item->code,'category'=>$item->category,'name'=>$item->name,
            'description'=>$item->description,'pricing_model'=>$item->pricing_model,'unit'=>$item->unit,
            'internal_cost'=>$cost,'recommended_price'=>$sell,
            'minimum_price'=>$item->minimum_price !== null ? (float)$item->minimum_price : null,
            'gross_margin'=>$sell > 0 ? round(($sell - $cost) / $sell * 100, 1) : null,
            'dependencies'=>$item->dependencies ?: [],'upsell_notes'=>$item->upsell_notes,
            'sales_notes'=>$item->sales_notes,'is_active'=>(bool)$item->is_active,'sort_order'=>(int)$item->sort_order,
        ];
    }
}
