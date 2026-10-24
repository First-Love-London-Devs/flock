<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public camp registration (RSVP, no payment) for the Living Waters & First
 * Love Camp. One row per registrant. country/church point at the tenant's
 * Groups (country-type and stream-type), nullOnDelete so a reorganised church
 * never deletes an RSVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camp_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->foreignId('country_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('church_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->json('attending_dates')->nullable();
            $table->string('travelling_from')->nullable();
            $table->boolean('needs_accommodation')->default(false);
            $table->boolean('bringing_children')->default(false);
            $table->unsignedSmallInteger('number_of_children')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('church_group_id');
            $table->index('country_group_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camp_registrations');
    }
};
