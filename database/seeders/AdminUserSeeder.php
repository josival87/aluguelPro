<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['login' => env('ADMIN_INITIAL_LOGIN', 'admin')], [
            'name' => 'Administrador AlugaPro',
            'email' => env('ADMIN_INITIAL_EMAIL', 'admin@alugapro.local'),
            'phone' => '(81) 99999-0000',
            'role' => 'admin',
            'active' => true,
            'password' => env('ADMIN_INITIAL_PASSWORD', 'AlugaPro@2026'),
        ]);
    }
}
