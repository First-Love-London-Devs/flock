<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birthday_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('leader_id')->constrained('leaders')->cascadeOnDelete();
            $table->date('notification_date');
            $table->enum('notification_type', ['today', 'tomorrow', 'one_week']);
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'leader_id', 'notification_date', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_notifications');
    }
};
