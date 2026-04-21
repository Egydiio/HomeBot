<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@homebot.app'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info("Usuário admin: {$user->email} (senha: password)");
    }
}
