<?php

namespace Tests\Unit;

use App\Support\ProspectIndexFilters;
use Tests\TestCase;

class ProspectIndexFiltersTest extends TestCase
{
    private const STATUSES = ['baru', 'dihubungi', 'deal'];

    public function test_normalize_bounds_search_and_whitelists_sort(): void
    {
        $f = ProspectIndexFilters::normalize([
            'q' => str_repeat('a', 300), 'sort' => 'deleted_at;DROP TABLE users',
            'priority' => 'unknown', 'status' => 'deal', 'followup' => 'due',
        ], true, self::STATUSES);
        $this->assertSame(120, mb_strlen($f['q']));
        $this->assertSame('score', $f['sort']);
        $this->assertSame('', $f['priority']);
        $this->assertSame('deal', $f['status']);
        $this->assertSame('due', $f['followup']);
    }

    public function test_admin_assignment_is_never_accepted_for_account_executive(): void
    {
        $sales = ProspectIndexFilters::normalize(['assigned_to' => '25'], false, self::STATUSES);
        $admin = ProspectIndexFilters::normalize(['assigned_to' => '25'], true, self::STATUSES);
        $this->assertSame('', $sales['assigned_to']);
        $this->assertSame('25', $admin['assigned_to']);
    }

    public function test_query_groups_text_search_and_keeps_tenant_scope(): void
    {
        $filters = ProspectIndexFilters::normalize([
            'q' => '62812', 'import_batch' => '17', 'priority' => 'tinggi', 'sort' => 'value',
        ], true, self::STATUSES);
        $query = \App\Models\Prospect::query()->where('organization_id', 15);
        $sql = ProspectIndexFilters::apply($query, $filters, true)->toSql();
        $this->assertStringContainsString('organization_id', $sql);
        $this->assertStringContainsString('import_batch_id', $sql);
        $this->assertStringContainsString('phone_normalized', $sql);
        $this->assertStringContainsString('estimated_deal_value', $sql);
        $this->assertStringNotContainsString('DROP TABLE', $sql);
    }

    public function test_followup_sort_places_null_last_using_whitelisted_expression(): void
    {
        $filters = ProspectIndexFilters::normalize(['sort' => 'followup'], false, self::STATUSES);
        $sql = ProspectIndexFilters::apply(\App\Models\Prospect::query(), $filters, false)->toSql();
        $this->assertStringContainsString('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END ASC', $sql);
    }

    public function test_import_batch_requires_positive_integer_and_array_inputs_are_safe(): void
    {
        $valid = ProspectIndexFilters::normalize(['import_batch' => '17'], true, self::STATUSES);
        $bad = ProspectIndexFilters::normalize(['import_batch' => '17 OR 1=1', 'q' => ['invalid'], 'sort' => ['value']], true, self::STATUSES);
        $this->assertSame('17', $valid['import_batch']);
        $this->assertSame('', $bad['import_batch']);
        $this->assertSame('', $bad['q']);
        $this->assertSame('score', $bad['sort']);
    }
}
