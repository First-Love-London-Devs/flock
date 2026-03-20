<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('group_type_id')->constrained('group_types')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->unsignedBigInteger('leader_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('meeting_day')->nullable();
            $table->time('meeting_time')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('group_type_id');
            $table->index('parent_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
