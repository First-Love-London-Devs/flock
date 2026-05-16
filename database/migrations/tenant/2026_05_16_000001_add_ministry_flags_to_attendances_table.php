<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ministry groups track three dimensions instead of plain attended:
     * Attended, Ministered, Rehearsed. These are additive booleans —
     * non-ministry groups simply leave them false.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('ministered')->default(false)->after('attended');
            $table->boolean('rehearsed')->default(false)->after('ministered');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['ministered', 'rehearsed']);
        });
    }
};
