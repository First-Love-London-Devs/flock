<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@poimen.co.uk'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Flock2026!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
