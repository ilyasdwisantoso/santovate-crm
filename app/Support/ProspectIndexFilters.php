<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * URL filters for the prospect listing. Tenant access is always enforced by
 * Prospect::visibleTo($user), independently of these optional UI filters.
 */
final class ProspectIndexFilters
{
    public const SORTS = ['score', 'recent', 'followup', 'value'];
    private const QUALIFICATIONS = ['new', 'contacted', 'qualified', 'unqualified', 'nurturing'];
    private const PRIORITIES = ['tinggi', 'sedang', 'rendah'];

    private static function string(array $query, string $key): string
    {
        $value = $query[$key] ?? null;
        return is_string($value) ? trim($value) : '';
    }

    public static function normalize(array $query, bool $isAdmin, array $allowedStatuses): array
    {
        $q = mb_substr(self::string($query, 'q'), 0, 120);
        $priority = self::string($query, 'priority');
        $status = self::string($query, 'status');
        $qualification = self::string($query, 'qualification_status');
        $assigned = self::string($query, 'assigned_to');
        $batch = self::string($query, 'import_batch');
        $sort = self::string($query, 'sort');

        return [
            'q' => $q,
            'entity_type'=>in_array(self::string($query,'entity_type'),['business','individual'],true)?self::string($query,'entity_type'):'',
            'priority' => in_array($priority, self::PRIORITIES, true) ? $priority : '',
            'status' => in_array($status, $allowedStatuses, true) ? $status : '',
            'qualification_status' => in_array($qualification, self::QUALIFICATIONS, true) ? $qualification : '',
            'assigned_to' => $isAdmin && ($assigned === 'unassigned' || (preg_match('/^[1-9][0-9]{0,9}$/', $assigned) === 1)) ? $assigned : '',
            'followup' => self::string($query, 'followup') === 'due' ? 'due' : '',
            'import_batch' => preg_match('/^[1-9][0-9]{0,9}$/', $batch) === 1 ? $batch : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'score',
        ];
    }

    public static function apply(Builder $query, array $filters, bool $isAdmin): Builder
    {
        if ($term = $filters['q']) {
            $digits = preg_replace('/\D+/', '', $term);
            $query->where(function (Builder $sub) use ($term, $digits) {
                foreach (array_merge(['company_name', 'city', 'contact_name', 'email', 'service', 'phone'], \App\Models\Prospect::hasNativeIdentitySchema()?['individual_name']:[]) as $column) {
                    $sub->orWhere($column, 'like', '%'.$term.'%');
                }
                if ($digits !== '') $sub->orWhere('phone_normalized', 'like', '%'.$digits.'%');
            });
        }
        if (($filters['entity_type'] ?? '') !== '') {
            if (\App\Models\Prospect::hasNativeIdentitySchema()) $query->where('entity_type',$filters['entity_type']);
            elseif ($filters['entity_type'] === 'individual') $query->whereRaw('1=0');
        }
        if ($filters['priority'] !== '') $query->where('priority', $filters['priority']);
        if ($filters['status'] !== '') $query->where('status', $filters['status']);
        if ($filters['qualification_status'] !== '') $query->where('qualification_status', $filters['qualification_status']);
        if ($isAdmin && $filters['assigned_to'] !== '') {
            if ($filters['assigned_to'] === 'unassigned') $query->whereNull('assigned_to');
            else $query->where('assigned_to', (int) $filters['assigned_to']);
        }
        if ($filters['followup'] === 'due') {
            $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay());
        }
        if ($filters['import_batch'] !== '') $query->where('import_batch_id', (int) $filters['import_batch']);

        switch ($filters['sort']) {
            case 'recent':
                $query->orderByDesc('updated_at');
                break;
            case 'followup':
                // ASC puts NULL first on MySQL and SQLite unless explicitly handled.
                $query->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('next_follow_up_at');
                break;
            case 'value':
                $query->orderByDesc('estimated_deal_value');
                break;
            default:
                $query->orderByDesc('total_score')->orderByDesc('updated_at');
        }
        return $query->orderByDesc('id');
    }
}
