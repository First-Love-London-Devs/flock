<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('level')->default(0);
            $table->string('color', 7)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('tracks_attendance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('level');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_types');
    }
};
