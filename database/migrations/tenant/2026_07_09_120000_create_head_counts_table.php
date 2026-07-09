<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_attendance')->default(0);
            $table->unsignedInteger('first_timer_count')->default(0);
            $table->unsignedInteger('visitor_count')->default(0);
            $table->string('submitter_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // One head count per bacenta per day.
            $table->unique(['group_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('head_counts');
    }
};
