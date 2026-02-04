<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResponderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $responders = [
            [
                'first_name' => 'John',
                'middle_name' => 'Emergency',
                'last_name' => 'Responder',
                'ext_name' => 'Jr.',
                'username' => 'john_responder',
                'email' => 'john.responder@rescuelink.com',
                'password' => 'Responder123!',
            ],
            [
                'first_name' => 'Maria',
                'middle_name' => 'Rescue',
                'last_name' => 'Medic',
                'ext_name' => null,
                'username' => 'maria_medic',
                'email' => 'maria.medic@rescuelink.com',
                'password' => 'Responder123!',
            ],
            [
                'first_name' => 'Robert',
                'middle_name' => 'Fire',
                'last_name' => 'Rescuer',
                'ext_name' => 'III',
                'username' => 'robert_rescuer',
                'email' => 'robert.rescuer@rescuelink.com',
                'password' => 'Responder123!',
            ],
            [
                'first_name' => 'Sarah',
                'middle_name' => 'Paramedic',
                'last_name' => 'Emergency',
                'ext_name' => null,
                'username' => 'sarah_paramedic',
                'email' => 'sarah.paramedic@rescuelink.com',
                'password' => 'Responder123!',
            ],
            [
                'first_name' => 'Michael',
                'middle_name' => 'Ambulance',
                'last_name' => 'Driver',
                'ext_name' => null,
                'username' => 'michael_driver',
                'email' => 'michael.driver@rescuelink.com',
                'password' => 'Responder123!',
            ],
        ];

        $createdCount = 0;
        foreach ($responders as $responder) {
            if (!User::where('email', $responder['email'])->exists()) {
                User::create([
                    'first_name' => $responder['first_name'],
                    'middle_name' => $responder['middle_name'],
                    'last_name' => $responder['last_name'],
                    'ext_name' => $responder['ext_name'],
                    'username' => $responder['username'],
                    'email' => $responder['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($responder['password']),
                    'role' => 'responder',
                    'remember_token' => Str::random(10),
                ]);
                
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $this->command->info("✅ {$createdCount} responders created successfully!");
            $this->command->info("📧 Emails: from john.responder@rescuelink.com to michael.driver@rescuelink.com");
            $this->command->info("🔐 Password for all responders: Responder123!");
        } else {
            $this->command->warn('⚠️ All responders already exist. Skipping...');
        }
    }
}