<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a domain narrow what you see when you come in through it.
 *
 * The European group runs 40 churches across eight countries out of one
 * tenant, because a tenant is a database and "the group sees all" needs
 * one. But people think of themselves as belonging to a country, and the
 * app already asks for a subdomain at login — so sweden.church-stack.com
 * should land a Swedish leader in Sweden rather than at the top of a
 * forty-church tree.
 *
 * A group NAME rather than an id, on purpose: domains live in the central
 * database and groups live in each tenant's own, so an id here could not
 * be a foreign key and would silently rot if the row it points at were
 * ever recreated. The name is resolved to a group inside the tenant at
 * request time, and a name that matches nothing simply means no narrowing.
 *
 * This can only narrow, never widen. See DomainScope for that rule; it is
 * the part that matters, because a domain is just a string anyone can type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('scope_group_name')->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('scope_group_name');
        });
    }
};
