<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a miscounted total be corrected, and say so on the record.
 *
 * The counter is a tap log plus a running total. Ushers miscount, and until
 * now the only remedy was for someone with database access to change the
 * number, which left a figure that looked exactly like one somebody had
 * actually counted.
 *
 * A corrected total is a different kind of fact from a counted one, so it
 * carries who changed it, when, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_counters', function (Blueprint $table) {
            $table->timestamp('corrected_at')->nullable()->after('reset_at');
            $table->string('corrected_by')->nullable()->after('corrected_at');
            $table->text('correction_note')->nullable()->after('corrected_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_counters', function (Blueprint $table) {
            $table->dropColumn(['corrected_at', 'corrected_by', 'correction_note']);
        });
    }
};
