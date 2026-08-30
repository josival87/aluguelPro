<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class AdminGroupContext
{
    public static function groupId(?User $user = null): ?int
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! $user->isAdmin() || $user->group_id === null) {
            return null;
        }

        return (int) $user->group_id;
    }

    public static function isRestricted(?User $user = null): bool
    {
        return self::groupId($user) !== null;
    }
}
