<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            return;
        }

        if (Admin::query()->where('email', $email)->exists()) {
            return;
        }

        Admin::query()->create([
            'name' => 'Администратор',
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }
}
