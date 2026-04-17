<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_member_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_summary_id')->constrained('attendance_summaries')->cascadeOnDelete();
            $table->foreignId('non_member_id')->constrained('non_members')->cascadeOnDelete();
            $table->boolean('attended')->default(true);
            $table->boolean('is_first_timer')->default(false);
            $table->timestamps();

            $table->unique(['attendance_summary_id', 'non_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_member_attendances');
    }
};
