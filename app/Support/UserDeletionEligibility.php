<?php

namespace App\Support;

final class UserDeletionEligibility
{
    public static function allows(bool $isSelf, bool $isPlatformAdmin, bool $isLastAdmin, int $historyTotal): bool
    {
        return !$isSelf && !$isPlatformAdmin && !$isLastAdmin && $historyTotal === 0;
    }
}
