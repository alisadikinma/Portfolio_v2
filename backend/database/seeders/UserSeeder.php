<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Ali Sadikin',
            'email' => 'admin@alisadikinma.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Passw0rd'),
        ]);

        // Create Second Admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@portfolio.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $this->command->info('✅ Admin users created successfully!');
        $this->command->info('   1) Email: admin@alisadikinma.com | Password: Passw0rd');
        $this->command->info('   2) Email: admin@portfolio.com | Password: password123');
    }
}
