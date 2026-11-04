<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The camp page now serves the Copenhagen Camp (Dec 2026) in place of the
 * Basel camp (Nov 2026). Registrations from both live in one table, so each
 * row is tagged with the camp it was taken for, and the Copenhagen form asks
 * for the registrant's church (a short fixed list) instead of country +
 * denomination. The old columns stay for the Basel rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camp_registrations', function (Blueprint $table) {
            $table->string('camp')->nullable()->index()->after('email');
            $table->string('church')->nullable()->index()->after('camp');
        });

        // Everything registered before this migration came through the Basel
        // (Living Waters & First Love Camp) form.
        DB::table('camp_registrations')->whereNull('camp')->update(['camp' => 'basel-2026']);
    }

    public function down(): void
    {
        Schema::table('camp_registrations', function (Blueprint $table) {
            $table->dropIndex(['camp']);
            $table->dropIndex(['church']);
            $table->dropColumn(['camp', 'church']);
        });
    }
};
