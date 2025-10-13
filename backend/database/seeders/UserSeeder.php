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

        $this->command->info('✅ Admin user created successfully!');
        $this->command->info('   Email: admin@alisadikinma.com');
        $this->command->info('   Password: Passw0rd');
    }
}
