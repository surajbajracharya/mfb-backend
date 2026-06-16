<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@meditationforbeginners.com'],
            [
                'name'            => 'Admin',
                'password'        => Hash::make('admin123456'),
                'email_verified_at' => now(),
                'gdpr_consent'    => true,
                'gdpr_consent_at' => now(),
            ]
        );

        $admin->assignRole('admin');
        echo "Admin user created: admin@meditationforbeginners.com / admin123456\n";
    }
}