<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Collection;

class ImportAssignmentService
{
    public const MODE_FILE = 'file';
    public const MODE_SINGLE = 'single';
    public const MODE_ROUND_ROBIN = 'round_robin';
    public const MODE_UNASSIGNED = 'unassigned';

    public const MODES = [
        self::MODE_FILE,
        self::MODE_SINGLE,
        self::MODE_ROUND_ROBIN,
        self::MODE_UNASSIGNED,
    ];

    /** @return Collection<int,User> */
    public function activeSales(): Collection
    {
        return User::query()
            ->where('role', 'sales')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function resolveFileSales(?string $email): ?User
    {
        if (!$email) {
            return null;
        }

        return User::query()
            ->where('role', 'sales')
            ->where('is_active', true)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first(['id', 'name', 'email']);
    }

    /**
     * @param array{mode:string,single_sales_id?:int|null,round_robin_sales_ids?:array<int,int>} $options
     * @return array{user_id:int|null,user:User|null,next_round_robin_index:int}
     */
    public function resolve(
        array $row,
        array $options,
        int $roundRobinIndex,
        ?Prospect $existing = null,
    ): array {
        $mode = $options['mode'] ?? self::MODE_FILE;

        if ($mode === self::MODE_UNASSIGNED) {
            return ['user_id' => null, 'user' => null, 'next_round_robin_index' => $roundRobinIndex];
        }

        if ($mode === self::MODE_SINGLE) {
            $user = $this->activeSales()->firstWhere('id', (int)($options['single_sales_id'] ?? 0));
            return ['user_id' => $user?->id, 'user' => $user, 'next_round_robin_index' => $roundRobinIndex];
        }

        if ($mode === self::MODE_ROUND_ROBIN) {
            $allowedIds = collect($options['round_robin_sales_ids'] ?? [])->map(fn ($id) => (int)$id)->values();
            $sales = $this->activeSales()->whereIn('id', $allowedIds)->values();
            if ($sales->isEmpty()) {
                return ['user_id' => null, 'user' => null, 'next_round_robin_index' => $roundRobinIndex];
            }
            $user = $sales[$roundRobinIndex % $sales->count()];
            return ['user_id' => $user->id, 'user' => $user, 'next_round_robin_index' => $roundRobinIndex + 1];
        }

        $user = $this->resolveFileSales($row['assigned_sales_email'] ?? null);
        return [
            'user_id' => $user?->id ?? $existing?->assigned_to,
            'user' => $user,
            'next_round_robin_index' => $roundRobinIndex,
        ];
    }
}
