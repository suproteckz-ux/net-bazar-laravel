<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name     = env('ADMIN_NAME', 'Admin');

        if (empty($email) || empty($password)) {
            $this->command->error('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before running this seeder.');
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make($password),
                'is_admin' => true,
            ]
        );

        $this->command->info("Admin user [{$email}] created or updated.");
    }
}
