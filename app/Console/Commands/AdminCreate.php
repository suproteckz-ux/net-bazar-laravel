<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminCreate extends Command
{
    protected $signature   = 'admin:create';
    protected $description = 'Create or update an admin user interactively';

    public function handle(): int
    {
        $email    = $this->ask('Admin email');
        $name     = $this->ask('Admin name', 'Admin');
        $password = $this->secret('Admin password');

        if (empty($email) || empty($password)) {
            $this->error('Email and password are required.');
            return Command::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make($password),
                'is_admin' => true,
            ]
        );

        $this->info("Admin [{$email}] created/updated successfully.");
        return Command::SUCCESS;
    }
}
