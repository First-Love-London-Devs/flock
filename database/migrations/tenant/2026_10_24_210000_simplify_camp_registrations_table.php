<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The camp registration form no longer links to the tenant's Groups. Country is
 * now a fixed short list and "church" becomes a free-text denomination, and the
 * per-day attendance question is gone.
 *
 * We rebuild the table rather than drop the group FK columns in place, because
 * dropping indexed/foreign-key columns is not portable to the SQLite the test
 * suite runs on. The registrant's core details are carried across; only the
 * group links (country_group_id/church_group_id) and attending_dates are lost,
 * which is exactly what is being removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camp_registrations_new', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('denomination')->nullable();
            $table->string('travelling_from')->nullable();
            $table->boolean('needs_accommodation')->default(false);
            $table->boolean('bringing_children')->default(false);
            $table->unsignedSmallInteger('number_of_children')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('country');
            $table->index('created_at');
        });

        if (Schema::hasTable('camp_registrations')) {
            $carry = ['id', 'full_name', 'phone_number', 'email', 'travelling_from',
                'needs_accommodation', 'bringing_children', 'number_of_children', 'notes',
                'created_at', 'updated_at'];

            DB::table('camp_registrations')->orderBy('id')->chunk(200, function ($rows) use ($carry) {
                $payload = $rows->map(fn ($r) => collect((array) $r)->only($carry)->all())->all();
                if ($payload) {
                    DB::table('camp_registrations_new')->insert($payload);
                }
            });

            Schema::dropIfExists('camp_registrations');
        }

        Schema::rename('camp_registrations_new', 'camp_registrations');
    }

    public function down(): void
    {
        // One-way: the dropped group links and per-day selections are not restored.
    }
};
