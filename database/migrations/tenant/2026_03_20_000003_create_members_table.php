<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone_number', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('picture')->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->string('occupation')->nullable();
            $table->date('member_since')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
