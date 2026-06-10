<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capture street name and postal code separately, alongside the existing
     * free-text address.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('street_name')->nullable()->after('address');
            $table->string('postal_code')->nullable()->after('street_name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['street_name', 'postal_code']);
        });
    }
};
