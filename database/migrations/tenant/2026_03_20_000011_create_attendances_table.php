<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_summary_id')->constrained('attendance_summaries')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->boolean('attended')->default(false);
            $table->boolean('is_first_timer')->default(false);
            $table->boolean('is_visitor')->default(false);
            $table->timestamps();

            $table->unique(['attendance_summary_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
