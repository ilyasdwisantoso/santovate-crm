<?php

namespace App\Services;

final class TeamInvitationTokens
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function valid(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
    }

    public static function digest(string $token): string
    {
        return hash('sha256', $token);
    }
}
