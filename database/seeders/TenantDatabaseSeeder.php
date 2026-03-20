<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultGroupTypesSeeder::class,
            DefaultRolesSeeder::class,
            DefaultSettingsSeeder::class,
        ]);

        // Create default admin user for the tenant
        $email = tenant()?->contact_email ?? 'admin@church.com';
        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Church Admin',
                'password' => Hash::make('Flock2026!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
