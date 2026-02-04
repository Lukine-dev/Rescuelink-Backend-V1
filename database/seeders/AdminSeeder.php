<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'first_name' => 'Administrator',
                'middle_name' => 'System',
                'last_name' => 'One',
                'ext_name' => null,
                'username' => 'admin1',
                'email' => 'admin1@rescuelink.com',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
            ],
            [
                'first_name' => 'Administrator',
                'middle_name' => 'System',
                'last_name' => 'Two',
                'ext_name' => null,
                'username' => 'admin2',
                'email' => 'admin2@rescuelink.com',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
            ],
        ];

        foreach ($admins as $adminData) {
            if (!User::where('email', $adminData['email'])->exists()) {
                User::create(array_merge($adminData, [
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                ]));
                
                $this->command->info("Admin created: {$adminData['username']}");
            }
        }
    }
}