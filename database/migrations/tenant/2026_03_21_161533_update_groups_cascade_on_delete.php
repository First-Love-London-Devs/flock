<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['group_type_id']);
            $table->foreign('group_type_id')->references('id')->on('group_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['group_type_id']);
            $table->foreign('group_type_id')->references('id')->on('group_types')->restrictOnDelete();
        });
    }
};
