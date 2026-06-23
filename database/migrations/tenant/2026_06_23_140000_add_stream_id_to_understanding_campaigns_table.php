<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('understanding_campaigns', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable()->after('id')->constrained('groups')->nullOnDelete();
            $table->index('stream_id');
        });
    }

    public function down(): void
    {
        Schema::table('understanding_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stream_id');
        });
    }
};
