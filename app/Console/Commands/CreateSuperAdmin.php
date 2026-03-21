<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create
        {--email=admin@flock.poimen.co.uk : Admin email}
        {--name=Super Admin : Admin name}
        {--password=Flock2026! : Admin password}';

    protected $description = 'Create a super admin user for the central admin panel';

    public function handle(): int
    {
        $email = $this->option('email');
        $name = $this->option('name');
        $password = $this->option('password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->info("Super admin created: {$user->email}");

        return self::SUCCESS;
    }
}
