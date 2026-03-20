<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leader_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_id')->constrained('leaders')->cascadeOnDelete();
            $table->foreignId('role_definition_id')->constrained('role_definitions')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['leader_id', 'role_definition_id', 'group_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leader_roles');
    }
};
