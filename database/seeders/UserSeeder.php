<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Quản trị', 'email' => 'admin@nhahang.test', 'role' => UserRole::Admin],
            ['name' => 'Quản lý', 'email' => 'manager@nhahang.test', 'role' => UserRole::Manager],
            ['name' => 'Phục vụ 1', 'email' => 'waiter@nhahang.test', 'role' => UserRole::Waiter],
            ['name' => 'Bếp', 'email' => 'kitchen@nhahang.test', 'role' => UserRole::Kitchen],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['email' => $user['email']], [
                ...$user,
                'password' => 'password',
                'email_verified_at' => now(),
            ]);
        }
    }
}
