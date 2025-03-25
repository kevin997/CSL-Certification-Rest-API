<?php

namespace App\Filament\Pages\Onboarding;

use App\Models\Environment;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessLearnerOnboarding extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Group Learner Onboarding';
    protected static ?string $navigationGroup = 'Onboarding';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.onboarding.business-learner-onboarding';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Business Environment')
                    ->schema([
                        Select::make('environment_id')
                            ->label('Business Environment')
                            ->options(Environment::whereHas('users', function ($query) {
                                $query->whereIn('environment_user.role', ['owner', 'individual_teacher']);
                            })->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ]),
                Section::make('Learner Information')
                    ->schema([
                        Toggle::make('send_invitations')
                            ->label('Send invitation emails to learners')
                            ->default(true),
                        Textarea::make('invitation_message')
                            ->label('Custom invitation message')
                            ->placeholder('Enter a custom message to include in the invitation emails'),
                        Repeater::make('learners')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('users', 'email'),
                                TextInput::make('employee_id')
                                    ->maxLength(50),
                                TextInput::make('department')
                                    ->maxLength(100),
                                TextInput::make('position')
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->required()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add Learner')
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
                // Get the environment
                $environment = Environment::findOrFail($data['environment_id']);
                
                // Get the company name from the environment owner or teacher
                $companyName = User::whereHas('environments', function ($query) use ($environment) {
                    $query->where('environments.id', $environment->id)
                        ->whereIn('environment_user.role', ['owner', 'individual_teacher']);
                })->value('company_name');
                
                $learnerIds = [];
                $sendInvitations = $data['send_invitations'] ?? false;
                $invitationMessage = $data['invitation_message'] ?? null;
                
                foreach ($data['learners'] as $learnerData) {
                    // Generate a random password
                    $password = Str::random(12);
                    
                    // Create the user
                    $user = User::create([
                        'name' => $learnerData['name'],
                        'email' => $learnerData['email'],
                        'password' => Hash::make($password),
                        'email_verified_at' => now(), // Auto-verify for simplicity
                        'company_name' => $companyName,
                    ]);
                    
                    // Associate the user with the environment as a learner
                    $environment->users()->attach($user->id, [
                        'role' => 'learner',
                        'permissions' => json_encode([
                            'access_courses' => true,
                        ]),
                        'joined_at' => now(),
                        'is_active' => true,
                        'credentials' => json_encode([
                            'username' => $user->email,
                            'environment_specific_id' => 'BLRN-' . (string)$user->id,
                            'employee_id' => $learnerData['employee_id'] ?? null,
                            'department' => $learnerData['department'] ?? null,
                            'position' => $learnerData['position'] ?? null,
                        ]),
                        'environment_email' => $user->email,
                        'environment_password' => Hash::make($password),
                        'use_environment_credentials' => true,
                    ]);
                    
                    $learnerIds[] = $user->id;
                    
                    // TODO: Send invitation email if requested
                    if ($sendInvitations) {
                        // Code to send invitation email with the password and custom message
                        // This would typically call a notification or mail class
                    }
                }
            });

            Notification::make()
                ->title('Business learners onboarded successfully')
                ->success()
                ->send();

            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to onboard business learners')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
