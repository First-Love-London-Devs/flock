<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    /**
     * `scope_group_name` names a group inside the tenant that requests
     * arriving on this domain are confined to — a country, for the
     * European group. Null means the domain narrows nothing, which is how
     * every existing domain behaves.
     *
     * A name rather than an id because domains live in the central
     * database and groups live in each tenant's own, so an id here could
     * never be a foreign key. See App\Services\DomainScope.
     */
    protected $fillable = ['domain', 'tenant_id', 'scope_group_name'];
}
