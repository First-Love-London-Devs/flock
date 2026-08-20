<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confine an admin panel login to one part of the tree.
 *
 * The panel has never filtered anything. Every Filament resource returns
 * whatever is in the tenant, which was harmless while a tenant held one
 * church and stopped being harmless the moment one held forty across eight
 * countries: the Belgian admin could read and edit Sweden.
 *
 * Null means the whole tenant, which is what every existing login gets, so
 * the group-wide admin keeps working exactly as before. A country admin
 * gets that country's group and everything under it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // after user_type, not is_super_admin: that column is on the
            // CENTRAL users table only, and this migration runs per tenant.
            $table->foreignId('scope_group_id')
                ->nullable()
                ->after('user_type')
                ->constrained('groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scope_group_id');
        });
    }
};
