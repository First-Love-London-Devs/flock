<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_attendance')->default(0);
            $table->unsignedInteger('visitor_count')->default(0);
            $table->unsignedInteger('first_timer_count')->default(0);
            $table->foreignId('submitted_by_leader_id')->nullable()->constrained('leaders')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'date']);
            $table->index('date');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
