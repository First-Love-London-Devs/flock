<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-defined service windows. During a window the scheduler nudges
        // the chosen role's holders to submit the head count, per Stream.
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('role_definition_id')->constrained('role_definitions')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday ... 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['day_of_week', 'is_active']);
        });

        // Sent-ledger so a schedule fires at most once per service occurrence.
        Schema::create('attendance_counter_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_schedule_id')->constrained('attendance_schedules')->cascadeOnDelete();
            $table->foreignId('stream_group_id')->constrained('groups')->cascadeOnDelete();
            $table->date('notification_date');
            $table->string('status')->default('sent'); // sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_schedule_id', 'notification_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_counter_notifications');
        Schema::dropIfExists('attendance_schedules');
    }
};
