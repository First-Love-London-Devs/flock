<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_new_convert')->default(false)->after('is_visitor');
        });

        Schema::table('non_member_attendances', function (Blueprint $table) {
            $table->boolean('is_new_convert')->default(false)->after('is_first_timer');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('is_new_convert');
        });

        Schema::table('non_member_attendances', function (Blueprint $table) {
            $table->dropColumn('is_new_convert');
        });
    }
};
