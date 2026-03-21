<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('nbs_status')->nullable()->after('occupation');
            $table->boolean('holy_ghost_baptism')->default(false)->after('nbs_status');
            $table->boolean('water_baptism')->default(false)->after('holy_ghost_baptism');
            $table->string('member_type')->nullable()->after('water_baptism');
            $table->boolean('profile_completed')->default(false)->after('member_type');
            $table->json('additional_info')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'nbs_status',
                'holy_ghost_baptism',
                'water_baptism',
                'member_type',
                'profile_completed',
                'additional_info',
            ]);
        });
    }
};
