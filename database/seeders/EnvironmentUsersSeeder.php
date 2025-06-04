<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnvironmentUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Environment::where('primary_domain', 'learning.csl-brands.com')->exists()) {

            // 1. Create users with specific roles (or find them if they already exist)
            $companyTeacher = User::firstOrCreate(
                ['email' => 'company.teacher@example.com'],
                [
                    'name' => 'Company Teacher',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::COMPANY_TEACHER,
                ]
            );

            $individualTeacher = User::firstOrCreate(
                ['email' => 'individual.teacher@example.com'],
                [
                    'name' => 'Individual Teacher',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::INDIVIDUAL_TEACHER,
                ]
            );

            $learner1 = User::firstOrCreate(
                ['email' => 'learner.one@example.com'],
                [
                    'name' => 'Learner One',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::LEARNER,
                ]
            );

            $learner2 = User::firstOrCreate(
                ['email' => 'learner.two@example.com'],
                [
                    'name' => 'Learner Two',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::LEARNER,
                ]
            );

            // 2. Create environments (or find them if they already exist)
            $environment1 = Environment::firstOrCreate(
                ['primary_domain' => 'learning.csl-brands.com'],
                [
                    'name' => 'Company Environment',
                    'owner_id' => $companyTeacher->id,
                    'is_active' => true,
                ]
            );

            $environment2 = Environment::firstOrCreate(
                ['primary_domain' => 'learning.cfpcsl.com'],
                [
                    'name' => 'Individual Environment',
                    'owner_id' => $individualTeacher->id,
                    'is_active' => true,
                ]
            );

            // 3. Onboard users into environments with specific roles and environment-specific credentials

            // User 3 (Learner 1) joins Environment 1 as company_learner with environment-specific credentials
            EnvironmentUser::firstOrCreate(
                [
                    'user_id' => $learner1->id,
                    'environment_id' => $environment1->id,
                ],
                [
                    'role' => 'company_learner',
                    'use_environment_credentials' => true,
                    'environment_email' => 'learner1@company-env.com',
                    'environment_password' => Hash::make('env1pass123'),
                ]
            );

            // User 3 (Learner 1) joins Environment 2 as learner with environment-specific credentials
            EnvironmentUser::firstOrCreate(
                [
                    'user_id' => $learner1->id,
                    'environment_id' => $environment2->id,
                ],
                [
                    'role' => 'learner',
                    'use_environment_credentials' => true,
                    'environment_email' => 'learner1@individual-env.com',
                    'environment_password' => Hash::make('env2pass123'),
                ]
            );

            // User 4 (Learner 2) joins Environment 1 as company_team_member with environment-specific credentials
            EnvironmentUser::firstOrCreate(
                [
                    'user_id' => $learner2->id,
                    'environment_id' => $environment1->id,
                ],
                [
                    'role' => 'company_team_member',
                    'use_environment_credentials' => true,
                    'environment_email' => 'team-member@company-env.com',
                    'environment_password' => Hash::make('team1pass123'),
                ]
            );

            // User 4 (Learner 2) joins Environment 2 as learner with environment-specific credentials
            EnvironmentUser::firstOrCreate(
                [
                    'user_id' => $learner2->id,
                    'environment_id' => $environment2->id,
                ],
                [
                    'role' => 'learner',
                    'use_environment_credentials' => true,
                    'environment_email' => 'learner2@individual-env.com',
                    'environment_password' => Hash::make('env2pass456'),
                ]
            );

            $this->command->info('Environment users seeded successfully with environment-specific credentials!');

            // Output some test credentials for reference
            $this->command->info('Test credentials for Environment 1:');
            $this->command->info('- Learner 1: learner1@company-env.com / env1pass123');
            $this->command->info('- Learner 2 (Team Member): team-member@company-env.com / team1pass123');

            $this->command->info('Test credentials for Environment 2:');
            $this->command->info('- Learner 1: learner1@individual-env.com / env2pass123');
            $this->command->info('- Learner 2: learner2@individual-env.com / env2pass456');
        }
    }
}
