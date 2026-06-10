<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the spiritual journey into a full growth track. New Believers School
     * (nbs_status) and the two baptisms already exist; add status columns for the
     * remaining milestones. Each uses the same states as nbs_status
     * (not_started / in_progress / completed); null is treated as not_started.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('strong_christian_status')->nullable()->after('nbs_status');
            $table->string('school_of_the_word_status')->nullable()->after('strong_christian_status');
            $table->string('school_of_solid_foundation_status')->nullable()->after('school_of_the_word_status');
            $table->string('school_of_victorious_living_status')->nullable()->after('school_of_solid_foundation_status');
            $table->string('school_of_apologetics_status')->nullable()->after('school_of_victorious_living_status');
            $table->string('school_of_evangelism_status')->nullable()->after('school_of_apologetics_status');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'strong_christian_status',
                'school_of_the_word_status',
                'school_of_solid_foundation_status',
                'school_of_victorious_living_status',
                'school_of_apologetics_status',
                'school_of_evangelism_status',
            ]);
        });
    }
};
