<?php

namespace App\Console\Commands;

use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnifyUserIdentities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'identity:unify 
                            {--dry-run : Run without making changes}
                            {--fix-orphans : Auto-create User records for orphaned environment_users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and unify user identities by ensuring all environment_user records are linked to a users record';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===========================================');
        $this->info('  Identity Unification - Story 1');
        $this->info('===========================================');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $fixOrphans = $this->option('fix-orphans');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Step 1: Audit current state
        $this->auditCurrentState();

        // Step 2: Find orphaned records (environment_user with NULL user_id)
        $orphanedByNullUserId = $this->findOrphanedByNullUserId();

        // Step 3: Find records where user_id exists but user doesn't
        $orphanedByMissingUser = $this->findOrphanedByMissingUser();

        // Step 4: Find records that can be linked by email
        $linkableByEmail = $this->findLinkableByEmail();

        // Summary
        $this->newLine();
        $this->info('===========================================');
        $this->info('  SUMMARY');
        $this->info('===========================================');
        $this->table(
            ['Category', 'Count', 'Action Needed'],
            [
                ['Orphaned (NULL user_id)', count($orphanedByNullUserId), $fixOrphans ? 'Will fix' : 'Run with --fix-orphans'],
                ['Orphaned (Missing User)', count($orphanedByMissingUser), $fixOrphans ? 'Will fix' : 'Run with --fix-orphans'],
                ['Linkable by Email', count($linkableByEmail), 'Will auto-link'],
            ]
        );

        if ($isDryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply changes.');
            return Command::SUCCESS;
        }

        // Step 5: Fix linkable records
        if (count($linkableByEmail) > 0) {
            $this->fixLinkableByEmail($linkableByEmail);
        }

        // Step 6: Fix orphans if requested
        if ($fixOrphans && (count($orphanedByNullUserId) > 0 || count($orphanedByMissingUser) > 0)) {
            $this->fixOrphans($orphanedByNullUserId, $orphanedByMissingUser);
        }

        // Final verification
        $this->newLine();
        $this->verifyResults();

        return Command::SUCCESS;
    }

    /**
     * Audit the current state of the database.
     */
    private function auditCurrentState(): void
    {
        $this->info('📊 Current Database State:');

        $totalUsers = User::count();
        $totalEnvUsers = EnvironmentUser::count();
        $linkedEnvUsers = EnvironmentUser::whereNotNull('user_id')->count();
        $unlinkedEnvUsers = EnvironmentUser::whereNull('user_id')->count();
        $withEnvCredentials = EnvironmentUser::where('use_environment_credentials', true)->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users (users table)', $totalUsers],
                ['Total Environment Users', $totalEnvUsers],
                ['Linked (has user_id)', $linkedEnvUsers],
                ['Unlinked (NULL user_id)', $unlinkedEnvUsers],
                ['Using Environment Credentials', $withEnvCredentials],
            ]
        );
    }

    /**
     * Find environment_user records with NULL user_id.
     */
    private function findOrphanedByNullUserId(): array
    {
        $orphans = DB::table('environment_user')
            ->whereNull('user_id')
            ->get()
            ->toArray();

        if (count($orphans) > 0) {
            $this->warn("⚠️  Found " . count($orphans) . " records with NULL user_id");

            if ($this->option('verbose')) {
                foreach ($orphans as $orphan) {
                    $this->line("   - ID: {$orphan->id}, Email: {$orphan->environment_email}");
                }
            }
        } else {
            $this->info("✅ No records with NULL user_id");
        }

        return $orphans;
    }

    /**
     * Find environment_user records where user_id points to non-existent user.
     */
    private function findOrphanedByMissingUser(): array
    {
        $orphans = DB::table('environment_user')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'environment_user.user_id');
            })
            ->get()
            ->toArray();

        if (count($orphans) > 0) {
            $this->warn("⚠️  Found " . count($orphans) . " records with invalid user_id (user doesn't exist)");

            if ($this->option('verbose')) {
                foreach ($orphans as $orphan) {
                    $this->line("   - ID: {$orphan->id}, user_id: {$orphan->user_id}, Email: {$orphan->environment_email}");
                }
            }
        } else {
            $this->info("✅ No records with invalid user_id");
        }

        return $orphans;
    }

    /**
     * Find environment_user records that can be linked by matching email.
     */
    private function findLinkableByEmail(): array
    {
        // Find environment_user records with NULL user_id but matching email in users table
        $linkable = DB::table('environment_user')
            ->whereNull('user_id')
            ->whereNotNull('environment_email')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.email', 'environment_user.environment_email');
            })
            ->get()
            ->toArray();

        if (count($linkable) > 0) {
            $this->info("🔗 Found " . count($linkable) . " records that can be linked by email");

            if ($this->option('verbose')) {
                foreach ($linkable as $record) {
                    $this->line("   - ID: {$record->id}, Email: {$record->environment_email}");
                }
            }
        }

        return $linkable;
    }

    /**
     * Link environment_user records to users by matching email.
     */
    private function fixLinkableByEmail(array $linkable): void
    {
        $this->info('🔧 Linking records by email...');
        $bar = $this->output->createProgressBar(count($linkable));
        $bar->start();

        $linked = 0;
        $failed = 0;

        foreach ($linkable as $record) {
            try {
                $user = User::where('email', $record->environment_email)->first();

                if ($user) {
                    DB::table('environment_user')
                        ->where('id', $record->id)
                        ->update(['user_id' => $user->id]);
                    $linked++;

                    Log::info("Identity Unification: Linked environment_user {$record->id} to user {$user->id}");
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Identity Unification: Failed to link environment_user {$record->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("   ✅ Linked: {$linked}, ❌ Failed: {$failed}");
    }

    /**
     * Create User records for orphaned environment_user records.
     */
    private function fixOrphans(array $nullUserIdOrphans, array $missingUserOrphans): void
    {
        $allOrphans = array_merge($nullUserIdOrphans, $missingUserOrphans);

        if (count($allOrphans) === 0) {
            return;
        }

        $this->info('🔧 Creating User records for orphans...');
        $bar = $this->output->createProgressBar(count($allOrphans));
        $bar->start();

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($allOrphans as $orphan) {
            try {
                $email = $orphan->environment_email;

                if (empty($email)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Check if user already exists with this email
                $existingUser = User::where('email', $email)->first();

                if ($existingUser) {
                    // Just link to existing user
                    DB::table('environment_user')
                        ->where('id', $orphan->id)
                        ->update(['user_id' => $existingUser->id]);
                    $created++;

                    Log::info("Identity Unification: Linked orphan {$orphan->id} to existing user {$existingUser->id}");
                } else {
                    // Create new user from environment_user data
                    $user = User::create([
                        'name' => $this->extractNameFromEmail($email),
                        'email' => $email,
                        'password' => $orphan->environment_password ?? bcrypt('changeme123'),
                        'email_verified_at' => $orphan->email_verified_at,
                    ]);

                    // Link the environment_user to the new user
                    DB::table('environment_user')
                        ->where('id', $orphan->id)
                        ->update(['user_id' => $user->id]);

                    $created++;

                    Log::info("Identity Unification: Created user {$user->id} for orphan {$orphan->id}");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Identity Unification: Failed to fix orphan {$orphan->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("   ✅ Fixed: {$created}, ⏭️ Skipped: {$skipped}, ❌ Failed: {$failed}");
    }

    /**
     * Extract a name from an email address.
     */
    private function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $localPart = $parts[0] ?? 'User';

        // Convert dots and underscores to spaces, capitalize words
        $name = str_replace(['.', '_', '-'], ' ', $localPart);
        $name = ucwords($name);

        return $name;
    }

    /**
     * Verify the results after fixing.
     */
    private function verifyResults(): void
    {
        $this->info('🔍 Verification:');

        $unlinked = DB::table('environment_user')
            ->whereNull('user_id')
            ->count();

        $invalidLinks = DB::table('environment_user')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'environment_user.user_id');
            })
            ->count();

        if ($unlinked === 0 && $invalidLinks === 0) {
            $this->info('✅ SUCCESS: All environment_user records are properly linked!');
            $this->info('   Query: SELECT * FROM environment_user WHERE user_id IS NULL → 0 rows');
        } else {
            $this->warn("⚠️  Still have issues:");
            $this->warn("   - Unlinked records: {$unlinked}");
            $this->warn("   - Invalid links: {$invalidLinks}");
            $this->warn("   Run with --fix-orphans to resolve.");
        }
    }
}
