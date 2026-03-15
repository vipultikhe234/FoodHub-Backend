<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@foodhub.com'],
            [
                'name' => 'FoodHub Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '0000000000',
                'address' => 'Main Office, FoodTown'
            ]
        );
        
        User::updateOrCreate(
            ['email' => 'user@foodhub.com'],
            [
                'name' => 'Jane Customer',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '1112223333',
                'address' => 'Customer Lane 45'
            ]
        );
    }
}
