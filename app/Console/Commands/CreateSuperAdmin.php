<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create
        {--email=admin@flock.church-stack.com : Admin email}
        {--name=Super Admin : Admin name}
        {--password= : Admin password (required)}';

    protected $description = 'Create a super admin user for the central admin panel';

    public function handle(): int
    {
        $email = $this->option('email');
        $name = $this->option('name');
        $password = $this->option('password');

        if (!$password) {
            $this->error('The --password option is required.');
            return self::FAILURE;
        }

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
