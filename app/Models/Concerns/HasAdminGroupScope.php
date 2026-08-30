<?php

namespace App\Models\Concerns;

use App\Models\Scopes\AdminGroupScope;

trait HasAdminGroupScope
{
    public static function bootHasAdminGroupScope(): void
    {
        static::addGlobalScope(new AdminGroupScope(
            static::ADMIN_GROUP_SCOPE_MODE,
            static::ADMIN_GROUP_SCOPE_KEY,
        ));
    }
}
