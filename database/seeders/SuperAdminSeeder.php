<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if superadmin already exists
        if (!User::where('role', 'superadmin')->exists()) {
            $superadmin = User::create([
                'first_name' => 'System',
                'middle_name' => 'Super',
                'last_name' => 'Administrator',
                'ext_name' => null,
                'username' => 'superadmin',
                'email' => 'superadmin@rescuelink.com',
                'email_verified_at' => now(),
                'user_phone_number' => '09999999999',
                'password' => Hash::make('password'), // Strong password
                'role' => 'superadmin',
                'remember_token' => Str::random(10),
            ]);

            $this->command->info("SuperAdmin created:");
            $this->command->info("Username: superadmin");
            $this->command->info("Email: superadmin@rescuelink.com");
            $this->command->info("Password: password");
        } else {
            $this->command->info("SuperAdmin already exists. Skipping...");
        }
    }
}