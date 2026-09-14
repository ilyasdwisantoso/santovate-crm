<?php

namespace App\Services;

use App\Models\BusinessConfiguration;
use App\Models\FollowUpTemplate;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Prospect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessConfigurationService
{
    public function pipelineFor(?Organization $organization): array
    {
        $pipeline = $organization?->businessConfiguration?->pipeline ?? [];
        if (!$pipeline) {
            return collect(Prospect::PIPELINE_STATUSES)->map(fn ($key) => ['key'=>$key,'label'=>Prospect::STATUSES[$key] ?? Str::headline($key)])->all();
        }
        return collect($pipeline)->filter(fn ($stage) => isset($stage['key']))->values()->all();
    }

    public function statusLabels(?Organization $organization): array
    {
        $labels = Prospect::STATUSES;
        foreach ($this->pipelineFor($organization) as $stage) {
            $labels[$stage['key']] = $stage['label'] ?? ($labels[$stage['key']] ?? Str::headline($stage['key']));
        }
        return $labels;
    }

    public function rule(?Organization $organization, string $key, mixed $default = null): mixed
    {
        return data_get($organization?->businessConfiguration?->follow_up_rules ?? [], $key, $default);
    }

    public function activate(Organization $organization, BusinessConfiguration $configuration): void
    {
        DB::transaction(function () use ($organization, $configuration) {
            $oldPipeline = $this->pipelineFor($organization->load('businessConfiguration'));
            $organization->update(['business_configuration_id'=>$configuration->id,'status'=>'active']);
            $organization->setRelation('businessConfiguration', $configuration);
            $newPipeline = $this->pipelineFor($organization);

            $newKeys = collect($newPipeline)->pluck('key')->all();
            $fallback = $newKeys[0] ?? 'baru';
            $oldKeys = collect($oldPipeline)->pluck('key')->values();
            $mapping = [];
            foreach ($oldKeys as $i => $oldKey) {
                $mapping[$oldKey] = $newKeys[$i] ?? $fallback;
            }
            foreach ($organization->prospects()->get() as $prospect) {
                if (!in_array($prospect->status, $newKeys, true) && isset($mapping[$prospect->status])) {
                    $prospect->update(['status'=>$mapping[$prospect->status]]);
                }
            }

            $this->ensureTemplates($organization, $configuration);
            $this->ensureDemoProducts($organization, $configuration);
        });
    }

    public function ensureTemplates(Organization $organization, BusinessConfiguration $configuration): void
    {
        $templates = collect($configuration->demo_templates ?? []);
        $required = [
            FollowUpTemplate::TYPE_LEAD_AGE => [
                'name'=>'Initial Outreach','wait_days'=>(int)$this->rule($organization,'lead_age_days',3),
                'message'=>'Halo {contact_name}, saya {ae_first_name} dari {business_name}. Saya ingin memperkenalkan solusi {service} yang mungkin relevan untuk {company_name}. Apakah saya boleh kirim overview singkat?',
            ],
            FollowUpTemplate::TYPE_NO_REPLY => [
                'name'=>'Follow-up Belum Ada Respons','wait_days'=>(int)$this->rule($organization,'no_reply_days',5),
                'message'=>'Halo {contact_name}, izin follow-up kembali terkait {service}. Jika masih relevan, saya siap bantu kirim ringkasan atau demo yang sesuai untuk {company_name}.',
            ],
            FollowUpTemplate::TYPE_CUSTOMER_REPLIED => [
                'name'=>'Customer Sudah Membalas','wait_days'=>0,
                'message'=>'Halo {contact_name}, terima kasih atas responsnya. Agar saya bisa menyiapkan solusi yang lebih tepat untuk {company_name}, boleh saya tahu kebutuhan atau kendala utama yang ingin dibenahi?',
            ],
        ];

        foreach ($required as $trigger => $fallback) {
            if (!$templates->contains(fn ($t) => ($t['trigger_type'] ?? null) === $trigger)) {
                $templates->push(['trigger_type'=>$trigger] + $fallback);
            }
        }

        foreach ($templates as $index => $item) {
            $trigger = $item['trigger_type'] ?? FollowUpTemplate::TYPE_NO_REPLY;
            $key = $organization->id.'-'.$configuration->key.'-'.$trigger.'-'.$index;
            FollowUpTemplate::updateOrCreate(
                ['organization_id'=>$organization->id,'key'=>$key],
                [
                    'business_configuration_id'=>$configuration->id,
                    'name'=>$item['name'] ?? Str::headline($trigger),
                    'trigger_type'=>$trigger,
                    'wait_days'=>(int)($item['wait_days'] ?? 0),
                    'message'=>$item['message'] ?? '',
                    'header_type'=>$item['header_type'] ?? 'none',
                    'image_url'=>$item['image_url'] ?? null,
                    'meta_template_name'=>$item['meta_template_name'] ?? null,
                    'meta_language'=>$item['meta_language'] ?? 'id',
                    'meta_status'=>$item['meta_status'] ?? 'draft',
                    'meta_category'=>$item['meta_category'] ?? 'marketing',
                    'body_parameters'=>$item['body_parameters'] ?? ['contact_name','company_name'],
                    'is_active'=>true,
                ]
            );
        }
    }

    public function ensureDemoProducts(Organization $organization, BusinessConfiguration $configuration): void
    {
        foreach ($configuration->demo_products ?? [] as $product) {
            if (blank($product['sku'] ?? null)) continue;
            Product::updateOrCreate(
                ['organization_id'=>$organization->id,'sku'=>$product['sku']],
                [
                    'name'=>$product['name'] ?? $product['sku'],
                    'variant'=>$product['variant'] ?? null,
                    'price'=>(int)($product['price'] ?? 0),
                    'image_url'=>$product['image_url'] ?? null,
                    'description'=>$product['description'] ?? null,
                    'is_active'=>true,
                ]
            );
        }
    }
}
