<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'first_name' => 'Juan',
                'middle_name' => 'Dela',
                'last_name' => 'Cruz',
                'ext_name' => null,
                'username' => 'juan_cruz',
                'email' => 'juan.cruz@example.com',
                'user_phone_number' => '09999999111',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Reyes',
                'ext_name' => null,
                'username' => 'maria_reyes',
                'email' => 'maria.reyes@example.com',
                'user_phone_number' => '09999999112',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Pedro',
                'middle_name' => 'Pablo',
                'last_name' => 'Gonzales',
                'ext_name' => 'Jr.',
                'username' => 'pedro_gonzales',
                'email' => 'pedro.gonzales@example.com',
                'user_phone_number' => '09999999113',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Ana',
                'middle_name' => 'Marie',
                'last_name' => 'Lopez',
                'ext_name' => null,
                'username' => 'ana_lopez',
                'email' => 'ana.lopez@example.com',
                'user_phone_number' => '09999999114',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Luis',
                'middle_name' => 'Miguel',
                'last_name' => 'Santos',
                'ext_name' => null,
                'username' => 'luis_santos',
                'email' => 'luis.santos@example.com',
                'user_phone_number' => '09999999115',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Sophia',
                'middle_name' => 'Grace',
                'last_name' => 'Martinez',
                'ext_name' => null,
                'username' => 'sophia_martinez',
                'email' => 'sophia.martinez@example.com',
                'user_phone_number' => '09999999116',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'James',
                'middle_name' => 'Robert',
                'last_name' => 'Wilson',
                'ext_name' => null,
                'username' => 'james_wilson',
                'email' => 'james.wilson@example.com',
                'user_phone_number' => '09999999117',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Emma',
                'middle_name' => 'Rose',
                'last_name' => 'Taylor',
                'ext_name' => null,
                'username' => 'emma_taylor',
                'email' => 'emma.taylor@example.com',
                'user_phone_number' => '09999999118',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Daniel',
                'middle_name' => 'Joseph',
                'last_name' => 'Anderson',
                'ext_name' => null,
                'username' => 'daniel_anderson',
                'email' => 'daniel.anderson@example.com',
                'user_phone_number' => '09999999119',
                'password' => 'User123!',
            ],
            [
                'first_name' => 'Olivia',
                'middle_name' => 'Jane',
                'last_name' => 'Thomas',
                'ext_name' => null,
                'username' => 'olivia_thomas',
                'email' => 'olivia.thomas@example.com',
                'user_phone_number' => '09999999120',
                'password' => 'User123!',
            ],
        ];

        $createdCount = 0;
        foreach ($users as $user) {
            if (!User::where('email', $user['email'])->exists()) {
                User::create([
                    'first_name' => $user['first_name'],
                    'middle_name' => $user['middle_name'],
                    'last_name' => $user['last_name'],
                    'ext_name' => $user['ext_name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'email_verified_at' => now(),
                    'user_phone_number' => $user['user_phone_number'],
                    'password' => Hash::make($user['password']),
                    'role' => 'user',
                    'remember_token' => Str::random(10),
                ]);
                
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $this->command->info("✅ {$createdCount} regular users created successfully!");
            $this->command->info("📧 Emails: from juan.cruz@example.com to olivia.thomas@example.com");
            $this->command->info("🔐 Password for all users: User123!");
        } else {
            $this->command->warn('⚠️ All users already exist. Skipping...');
        }
    }
}