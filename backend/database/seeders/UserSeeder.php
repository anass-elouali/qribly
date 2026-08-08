<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Youssef Amrani', 'email' => 'youssef@qribly.test'],
            ['name' => 'Sara Benali', 'email' => 'sara@qribly.test'],
            ['name' => 'Omar Alaoui', 'email' => 'omar@qribly.test'],
            ['name' => 'Salma Idrissi', 'email' => 'salma@qribly.test'],
            ['name' => 'Test User', 'email' => 'test@qribly.test'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}