<?php

namespace App\Models\Scopes;

use App\Support\AdminGroupContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class AdminGroupScope implements Scope
{
    public const DIRECT = 'direct';

    public const RELATION = 'relation';

    public const CLIENT = 'client';

    public function __construct(
        private readonly string $mode,
        private readonly string $key,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $groupId = AdminGroupContext::groupId();

        if ($groupId === null) {
            return;
        }

        if ($this->mode === self::DIRECT) {
            $builder->where($model->qualifyColumn($this->key), $groupId);

            return;
        }

        if ($this->mode === self::RELATION) {
            $builder->whereHas($this->key, function (Builder $related) use ($groupId): void {
                $related->where($related->getModel()->qualifyColumn('group_id'), $groupId);
            });

            return;
        }

        if ($this->mode === self::CLIENT) {
            $builder->where(function (Builder $query) use ($groupId, $model): void {
                $query
                    ->where($model->qualifyColumn($this->key), $groupId)
                    ->orWhereHas('leases.property', function (Builder $property) use ($groupId): void {
                        $property->where($property->getModel()->qualifyColumn('group_id'), $groupId);
                    });
            });
        }
    }
}
