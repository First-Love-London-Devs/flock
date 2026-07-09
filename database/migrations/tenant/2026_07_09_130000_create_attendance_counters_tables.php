<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-(stream, day) running summary — one row, incremented per tap.
        Schema::create('attendance_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('first_time_count')->default(0);
            $table->unsignedInteger('returning_count')->default(0);
            $table->unsignedInteger('regular_count')->default(0);
            $table->unsignedInteger('visitor_count')->default(0);
            $table->timestamp('reset_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'date']);
            $table->index('date');
        });

        // Append-only audit log — one row per tap (survives summary resets).
        Schema::create('attendance_count_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->date('date');
            $table->string('device_id')->nullable();
            $table->string('category');
            $table->timestamps();

            $table->index(['group_id', 'date']);
            $table->index(['device_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_count_entries');
        Schema::dropIfExists('attendance_counters');
    }
};
