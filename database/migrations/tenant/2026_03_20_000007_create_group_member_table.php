<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'member_id']);
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_member');
    }
};
