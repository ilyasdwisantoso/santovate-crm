<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Prospect;
use App\Services\ProspectIdentityService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CrmTestCase;

class ProspectIdentityTest extends CrmTestCase
{
    private function org(): Organization
    {
        return Organization::create(['name'=>'Identity test','slug'=>'identity-test']);
    }

    public function test_individual_is_native_and_never_has_a_company_key(): void
    {
        config()->set('santovate.crm.native_individuals_enabled', true);
        $data = app(ProspectIdentityService::class)->normalize(['entity_type'=>'individual','individual_name'=>' Nama Individu ']);
        $p = Prospect::create([...$data, 'organization_id'=>$this->org()->id]);
        $this->assertSame('Nama Individu', $p->display_name);
        $this->assertNull($p->company_name);
        $this->assertNull($p->company_key);
        $this->assertSame('individual', $p->fresh()->entity_type);
    }

    public function test_business_key_and_duplicate_override_storage_remain_compatible(): void
    {
        $org = $this->org();
        $first = Prospect::create(['organization_id'=>$org->id,'company_name'=>'PT Nama Sama','city'=>'Jakarta']);
        $second = Prospect::create(['organization_id'=>$org->id,'company_name'=>'PT Nama Sama','city'=>'Jakarta']);
        $this->assertSame('business', $first->fresh()->entity_type);
        $this->assertSame(Prospect::makeCompanyKey('PT Nama Sama','Jakarta',$org->id), $first->company_key);
        $this->assertSame($first->company_key, $second->company_key);
        $this->assertSame('PT Nama Sama', $first->display_name);
        $this->assertNull($first->individual_name);
    }

    public function test_same_individual_name_or_phone_does_not_become_a_unique_identity(): void
    {
        $org = $this->org();
        foreach (range(1,2) as $i) {
            Prospect::create(['organization_id'=>$org->id,'entity_type'=>'individual','individual_name'=>'Nama Sama','phone'=>'081234567890']);
        }
        $this->assertSame(2, Prospect::where('organization_id',$org->id)->count());
    }

    public function test_flag_off_blocks_new_individual_but_allows_existing_individual_updates(): void
    {
        $org = $this->org();
        $p = Prospect::create(['organization_id'=>$org->id,'entity_type'=>'individual','individual_name'=>'Nama Lama']);
        $identity = app(ProspectIdentityService::class);
        $data = $identity->normalize(['individual_name'=>'Nama Baru'], $p);
        $p->update($data);
        $this->assertSame('Nama Baru', $p->display_name);
        $this->expectException(ValidationException::class);
        $identity->normalize(['entity_type'=>'individual','individual_name'=>'Individu Baru']);
    }

    public function test_type_cannot_be_changed_on_an_existing_prospect(): void
    {
        $p = Prospect::create(['organization_id'=>$this->org()->id,'company_name'=>'PT Tetap']);
        $this->expectException(ValidationException::class);
        $p->update(['entity_type'=>'individual','individual_name'=>'Nama Orang','company_name'=>null]);
    }

    public function test_native_rollback_is_rejected_and_identity_data_survives(): void
    {
        $p = Prospect::create(['organization_id'=>$this->org()->id,'entity_type'=>'individual','individual_name'=>'Tetap Ada']);
        try {
            (require database_path('migrations/2026_10_16_090000_add_native_prospect_identity.php'))->down();
            $this->fail('Schema rollback must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forward-only', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('prospects','individual_name'));
        $this->assertSame('Tetap Ada', $p->fresh()->display_name);
    }
}
