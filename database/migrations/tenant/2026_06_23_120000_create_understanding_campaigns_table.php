<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('understanding_campaigns', function (Blueprint $table) {
            $table->id();
            $table->date('attended_on');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('street_name');
            $table->string('postal_code');
            $table->string('phone_number');
            $table->boolean('re_dedicating')->default(false);
            $table->boolean('first_time')->default(false);
            $table->string('who_invited');
            $table->foreignId('allocated_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->timestamps();

            $table->index('allocated_group_id');
            $table->index('attended_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('understanding_campaigns');
    }
};
