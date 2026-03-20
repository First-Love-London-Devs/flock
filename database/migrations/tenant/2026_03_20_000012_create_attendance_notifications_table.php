<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_group_id')->constrained('groups')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_attendance')->default(0);
            $table->string('notification_type', 50)->default('completion');
            $table->foreignId('leader_id')->nullable()->constrained('leaders')->nullOnDelete();
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['parent_group_id', 'date', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_notifications');
    }
};
