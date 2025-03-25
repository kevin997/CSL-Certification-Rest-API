<?php

namespace App\Filament\Pages\Onboarding;

use App\Models\Environment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessTeacherOnboarding extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Business Teacher Onboarding';
    protected static ?string $navigationGroup = 'Onboarding';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.onboarding.business-teacher-onboarding';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Primary Teacher Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email'),
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->required(),
                        TextInput::make('company_name')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Environment Information')
                    ->schema([
                        TextInput::make('environment_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('domain')
                            ->required()
                            ->maxLength(255)
                            ->unique('environments', 'primary_domain'),
                        ColorPicker::make('theme_color')
                            ->default('#4F46E5'),
                        TextInput::make('logo_url')
                            ->url()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->maxLength(1000),
                    ]),
                Section::make('Subscription Information')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(Plan::where('type', 'business')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Radio::make('billing_cycle')
                            ->options([
                                'monthly' => 'Monthly',
                                'annual' => 'Annual',
                            ])
                            ->default('monthly')
                            ->required(),
                    ]),
                Section::make('Additional Administrators')
                    ->schema([
                        Toggle::make('send_invitations')
                            ->label('Send invitation emails to administrators')
                            ->default(true),
                        Repeater::make('additional_admins')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('users', 'email'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Administrator')
                            ->reorderable(false)
                            ->collapsible(),
                    ]),
            ])
            ->statePath('data');
    }

    public function onboard(): void
    {
        $data = $this->form->getState();

        try {
            DB::transaction(function () use ($data) {
                // Create the primary user (business teacher)
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(), // Auto-verify for simplicity
                    'company_name' => $data['company_name'],
                ]);

                // Create the environment
                $environment = Environment::create([
                    'name' => $data['environment_name'],
                    'primary_domain' => $data['domain'],
                    'theme_color' => $data['theme_color'],
                    'logo_url' => $data['logo_url'] ?? null,
                    'favicon_url' => $data['logo_url'] ?? null, // Default to logo_url
                    'description' => $data['description'] ?? null,
                    'owner_id' => $user->id,
                    'is_active' => true,
                ]);

                // Associate the primary user with the environment as an owner
                $environment->users()->attach($user->id, [
                    'role' => 'owner',
                    'permissions' => json_encode([
                        'manage_users' => true,
                        'manage_courses' => true,
                        'manage_content' => true,
                        'manage_billing' => true,
                    ]),
                    'joined_at' => now(),
                    'is_active' => true,
                    'credentials' => json_encode([
                        'username' => $user->email,
                        'environment_specific_id' => 'BTCH-' . (string)$user->id,
                    ]),
                    'environment_email' => $user->email,
                    'environment_password' => Hash::make($data['password']),
                    'use_environment_credentials' => true,
                ]);

                // Get the plan
                $plan = Plan::findOrFail($data['plan_id']);

                // Create a subscription
                $subscription = Subscription::create([
                    'environment_id' => (string)$environment->id,
                    'plan_id' => (string)$plan->id,
                    'billing_cycle' => $data['billing_cycle'],
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $data['billing_cycle'] === 'monthly' ? now()->addMonth() : now()->addYear(),
                    'setup_fee_paid' => false, // Will be handled by the payment process
                ]);

                // Create additional administrators if provided
                $additionalAdminIds = [];
                $sendInvitations = $data['send_invitations'] ?? false;
                
                if (isset($data['additional_admins']) && is_array($data['additional_admins'])) {
                    foreach ($data['additional_admins'] as $adminData) {
                        // Create a random password
                        $password = Str::random(12);
                        
                        // Create the admin user
                        $admin = User::create([
                            'name' => $adminData['name'],
                            'email' => $adminData['email'],
                            'password' => Hash::make($password),
                            'company_name' => $data['company_name'],
                        ]);
                        
                        // Associate the admin with the environment
                        $environment->users()->attach($admin->id, [
                            'role' => 'admin',
                            'permissions' => json_encode([
                                'manage_users' => true,
                                'manage_courses' => true,
                                'manage_content' => true,
                                'manage_billing' => false, // Only the owner can manage billing
                            ]),
                            'joined_at' => now(),
                            'is_active' => true,
                            'credentials' => json_encode([
                                'username' => $admin->email,
                                'environment_specific_id' => 'BADM-' . (string)$admin->id,
                            ]),
                            'environment_email' => $admin->email,
                            'environment_password' => Hash::make($password),
                            'use_environment_credentials' => true,
                        ]);
                        
                        $additionalAdminIds[] = $admin->id;
                        
                        // TODO: Send invitation email if requested
                        if ($sendInvitations) {
                            // Code to send invitation email with the password
                            // This would typically call a notification or mail class
                        }
                    }
                }
            });

            Notification::make()
                ->title('Business teacher onboarded successfully')
                ->success()
                ->send();

            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to onboard business teacher')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
