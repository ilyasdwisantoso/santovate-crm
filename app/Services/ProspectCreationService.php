<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\ProspectAssignmentHistory;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class ProspectCreationService
{
    private ?Organization $lockedOrganization = null;

    public function __construct(
        private readonly ProspectIdentityService $identities,
        private readonly EntitlementService $entitlements,
    ) {}

    public function withinOrganization(User $actor, Closure $operation): mixed
    {
        if (DB::transactionLevel() !== 0 || $this->lockedOrganization) {
            throw new LogicException('Prospect creation must own its transaction before any quota reads.');
        }
        abort_unless($actor->organization_id && $actor->is_active, 403);
        return DB::transaction(function () use ($actor, $operation) {
            $this->lockedOrganization = Organization::query()->lockForUpdate()->findOrFail($actor->organization_id);
            try {
                return $operation($this->lockedOrganization);
            } finally {
                $this->lockedOrganization = null;
            }
        }, 3);
    }

    public function create(User $actor, array $data): Prospect
    {
        return $this->withinOrganization($actor, fn () => $this->createWithin($actor, $data, null, ['allow_marketing_opt_in'=>true]));
    }

    public function organization(): Organization
    {
        if (!$this->lockedOrganization || DB::transactionLevel() === 0) throw new LogicException('Organization lock is required.');
        return $this->lockedOrganization;
    }

    public function createWithin(User $actor, array $data, ?EntitlementService $entitlements = null, array $options = []): Prospect
    {
        $org = $this->organization();
        abort_unless((int)$actor->organization_id === (int)$org->id, 403);
        $data = $this->identities->normalize($data);
        $this->identities->assertReviewed($actor, $data);
        ($entitlements ?? $this->entitlements)->assertCanConsume($org, 'prospects', 1);

        $assigned = $actor->isAdmin() ? ($data['assigned_to'] ?? null) : $actor->id;
        if ($assigned && !User::query()->where('organization_id',$org->id)->where('role','sales')->where('is_active',true)->whereKey($assigned)->exists()) {
            throw ValidationException::withMessages(['assigned_to'=>'Account Executive tidak valid untuk workspace ini.']);
        }
        $productIds = array_values(array_unique($data['product_ids'] ?? []));
        if ($productIds && Product::where('organization_id',$org->id)->whereIn('id',$productIds)->count() !== count($productIds)) {
            throw ValidationException::withMessages(['product_ids'=>'Produk tidak valid untuk workspace ini.']);
        }
        $optIn = !empty($options['allow_marketing_opt_in']) && !empty($data['whatsapp_opt_in']);
        $status = $data['status'] ?? 'baru';
        unset($data['product_ids'],$data['whatsapp_opt_in'],$data['force_duplicate'],$data['assignment_reason'],
            $data['company_key'],$data['display_name'],$data['actual_deal_value'],
            $data['whatsapp_opt_in_at'],$data['whatsapp_opt_out_at']);
        $data['organization_id'] = $org->id;
        $data['created_by'] = $actor->id;
        $data['assigned_to'] = $assigned ?: null;
        $data['status'] = 'baru';
        if ($optIn) $data['whatsapp_opt_in_at'] = now();
        $p = Prospect::create($data);
        if ($productIds) $p->products()->sync(collect($productIds)->mapWithKeys(fn ($id) => [$id=>['is_primary'=>false]])->all());
        ProspectActivity::create([
            'prospect_id'=>$p->id,'user_id'=>$actor->id,'type'=>'note',
            'title'=>$options['activity_title'] ?? 'Prospek dibuat',
            'description'=>$options['activity_description'] ?? 'Prospek ditambahkan ke CRM.',
            'occurred_at'=>now(),
        ]);
        if ($p->assigned_to) ProspectAssignmentHistory::create([
            'organization_id'=>$org->id,'prospect_id'=>$p->id,'from_user_id'=>null,'to_user_id'=>$p->assigned_to,
            'changed_by'=>$actor->id,'reason'=>'Initial assignment',
        ]);
        if ($status !== 'baru') app(ProspectStageService::class)->transition($p,$status,$actor,'Form prospek');
        return $p;
    }
}
