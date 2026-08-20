<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Confine a Filament resource to the signed-in admin's part of the tree.
 *
 * The panel filtered nothing before this. Every resource returned whatever
 * was in the tenant, which looked fine while a tenant held one church and
 * stopped being fine the moment one held forty across eight countries.
 *
 * A resource says how it reaches a group, and this applies the confinement:
 *
 *     protected static function scopeColumn(): ?string { return 'group_id'; }
 *
 * or, when the link is not a plain column, by overriding applyGroupScope().
 *
 * An unscoped admin (scope_group_id null) is left completely alone, so the
 * group-wide login keeps seeing all forty churches.
 */
trait ScopesToAdminGroup
{
    /** The column on this resource's table holding the group id. */
    protected static function scopeColumn(): ?string
    {
        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyGroupScope(parent::getEloquentQuery());
    }

    protected static function applyGroupScope(Builder $query): Builder
    {
        $ids = User::currentScopeIds();

        // null means unrestricted. An empty collection means restricted to
        // nothing, which must still filter, or a misconfigured admin would
        // silently see everything.
        if ($ids === null) {
            return $query;
        }

        $column = static::scopeColumn();

        return $column
            ? $query->whereIn($query->getModel()->getTable().'.'.$column, $ids)
            : $query;
    }
}
