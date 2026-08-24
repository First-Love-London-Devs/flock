<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The country a request came in through, if it came in through one.
 *
 * The European group's 40 churches share a tenant, because a tenant is a
 * database and "the group sees all" needs one. Countries are a tier inside
 * that tree, and each has its own subdomain — the app already asks for one
 * at login — so belgium.church-stack.com should land you in Belgium rather
 * than at the top of a forty-church list.
 *
 * THE RULE THIS CLASS EXISTS TO ENFORCE: a domain can only ever narrow
 * what a leader sees, never widen it. A domain is a string anybody can
 * type, so it must not be able to grant anything. LeaderScopeService
 * intersects with this rather than substituting it, which means:
 *
 *   - a group admin at belgium.  sees Belgium, a useful lens
 *   - a Belgian leader at sweden. sees nothing, not Sweden
 *
 * A domain with no country set narrows nothing, so the group-wide address
 * and every existing tenant behave exactly as before.
 */
class DomainScope
{
    /** Resolved once per request; false means "looked and found none". */
    private static Collection|false|null $cached = null;

    /**
     * The connection holding the domains table.
     *
     * Domains are central and groups are per-tenant, so this class straddles
     * two databases and must say which one it means every time. Public so the
     * choice can be asserted: getting it wrong disables the whole class
     * silently rather than failing, which is how it went unnoticed.
     */
    public static function centralConnection(): string
    {
        return (new Domain)->getConnectionName()
            ?: config('tenancy.database.central_connection')
            ?: config('database.default');
    }

    /**
     * Group ids the current domain confines a request to, or null for no
     * confinement at all.
     */
    public static function groupIds(): ?Collection
    {
        if (self::$cached !== null) {
            return self::$cached === false ? null : self::$cached;
        }

        self::$cached = false;

        // No request at all: console commands, queued jobs, tests. Those
        // must not be silently narrowed to nothing.
        $host = Request::getHost();
        if (! $host) {
            return null;
        }

        /* The central tables are not always present. Feature tests migrate
           only database/migrations/tenant, and a queue worker can run with
           tenancy initialised and no central connection to hand. Asking for
           a table that does not exist would throw and take a request down
           over a feature that is meant to be optional.

           ⚠️ This check MUST name the central connection. It used to call
           Schema::hasTable(), which asks the DEFAULT connection — and inside
           an initialised tenant the default IS the tenant database, which has
           no domains table. So on every real request this returned false and
           the method bailed out returning null, which means "no confinement".
           Every domain rule in the app was quietly inert in production while
           its tests passed, because the tests all went through fake(). It
           surfaced as Belgium's public attendance counter listing Basel,
           Bern, Biel, Geneva and Zurich. */
        try {
            if (! Schema::connection(self::centralConnection())->hasTable('domains')) {
                return null;
            }

            $domain = Domain::where('domain', $host)->first();
        } catch (\Throwable) {
            // No central connection configured at all.
            return null;
        }

        $name = $domain?->scope_group_name;
        if (! $name) {
            return null;
        }

        /* Resolved inside the tenant, which is why this is a name and not
           an id: domains are central, groups are per-tenant. A name that
           matches nothing means no narrowing rather than an empty list —
           locking everyone out because a group was renamed would be a
           worse failure than showing them the whole tree. */
        $group = Group::where('name', $name)->first();
        if (! $group) {
            return null;
        }

        self::$cached = $group->allGroupIds();

        return self::$cached;
    }

    /**
     * Narrow a set of group ids to the country the request came in through.
     *
     * An intersection, never a substitution: coming in through belgium.
     * shows a group-wide admin Belgium, and shows a Belgian leader who
     * typed sweden. nothing at all. Returns the ids untouched when no
     * domain scope applies, which is every existing tenant and the
     * group-wide address.
     *
     * This lives here rather than in one of its callers because both
     * LeaderScopeService and AdminController have to apply it, and two
     * copies of a rule like this is how one of them ends up not having it.
     */
    public static function confine(Collection $ids): Collection
    {
        $confineTo = self::groupIds();

        return $confineTo === null ? $ids : $ids->intersect($confineTo)->values();
    }

    /** Tests and long-running workers need to be able to clear this. */
    public static function forget(): void
    {
        self::$cached = null;
    }

    /**
     * Confine to a named group directly, without a domain row.
     *
     * For tests, which run against a tenant-only database where `domains`
     * does not exist. It sets the same cached value the real lookup would
     * produce, so what is exercised afterwards is the intersection in
     * LeaderScopeService — which is the part that has to be right.
     */
    public static function fake(?string $groupName): void
    {
        if ($groupName === null) {
            self::$cached = false;

            return;
        }

        $group = Group::where('name', $groupName)->first();
        self::$cached = $group ? $group->allGroupIds() : false;
    }
}
