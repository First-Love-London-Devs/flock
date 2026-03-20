<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultGroupTypesSeeder::class,
            DefaultRolesSeeder::class,
            DefaultSettingsSeeder::class,
        ]);
    }
}
