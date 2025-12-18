<?php

namespace App\Console\Commands;

use App\Mail\EnvironmentSetupMail;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEnvironmentEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send-environment-setup 
                            {email : The email address to send to}
                            {environment : The environment ID or name}
                            {password : The admin password to include in the email}
                            {--name= : User name (required if creating new user)}
                            {--create-user : Create the user if they do not exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an environment setup email with credentials to a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $environmentIdentifier = $this->argument('environment');
        $password = $this->argument('password');
        $createUser = $this->option('create-user');
        $userName = $this->option('name');

        // Find or create the user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            if ($createUser) {
                if (!$userName) {
                    $userName = explode('@', $email)[0]; // Use email prefix as name
                }
                
                $user = User::create([
                    'name' => $userName,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
                
                $this->info("Created new user: {$user->name} ({$user->email})");
            } else {
                $this->error("User with email {$email} not found. Use --create-user to create them.");
                return Command::FAILURE;
            }
        }

        // Find the environment (by ID or name)
        $environment = Environment::where('id', $environmentIdentifier)
            ->orWhere('name', $environmentIdentifier)
            ->first();

        if (!$environment) {
            $this->error("Environment '{$environmentIdentifier}' not found.");
            
            // List available environments
            $environments = Environment::all(['id', 'name', 'primary_domain']);
            if ($environments->isNotEmpty()) {
                $this->info("\nAvailable environments:");
                foreach ($environments as $env) {
                    $this->line("  - ID: {$env->id}, Name: {$env->name}, Domain: {$env->primary_domain}");
                }
            }
            
            return Command::FAILURE;
        }

        $this->info("Sending environment setup email to {$email}...");
        $this->info("Environment: {$environment->name} (ID: {$environment->id})");
        $this->info("Domain: {$environment->primary_domain}");

        try {
            Mail::to($user->email)->send(new EnvironmentSetupMail(
                environment: $environment,
                user: $user,
                adminEmail: $email,
                adminPassword: $password,
            ));

            $this->info("✅ Environment setup email sent successfully to {$email}!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
