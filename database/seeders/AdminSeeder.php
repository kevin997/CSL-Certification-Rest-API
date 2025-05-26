<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the admin user for Filament.
     */
    public function run(): void
    {
        if (!User::where('email', 'admin@csl-certification.com')->exists()) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@csl-certification.com',
                'password' => Hash::make('Kwbi#1DG^FRE@1990!:$'), // Should be changed in production
                'email_verified_at' => now(),
                'role' => UserRole::SUPER_ADMIN,
            ]);
        }
    }
}
