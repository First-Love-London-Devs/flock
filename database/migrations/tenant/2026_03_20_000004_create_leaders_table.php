<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('members')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('notification_token')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaders');
    }
};
