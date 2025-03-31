<?php

namespace App\Filament\Pages\Onboarding;

use App\Models\Environment;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IndividualLearnerOnboarding extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Individual Learner Onboarding';
    protected static ?string $navigationGroup = 'Onboarding';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.onboarding.individual-learner-onboarding';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Learner Information')
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
                        Select::make('environment_id')
                            ->label('Environment')
                            ->options(Environment::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ]),
            ])
            ->statePath('data');
    }

    public function onboard(): void
    {
        $data = $this->form->getState();

        try {
            DB::transaction(function () use ($data) {
                // Create the user
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(), // Auto-verify for simplicity
                ]);

                // Get the environment
                $environment = Environment::findOrFail($data['environment_id']);

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
                        'environment_specific_id' => 'LRN-' . (string)$user->id,
                    ]),
                    'environment_email' => $user->email,
                    'environment_password' => Hash::make($data['password']),
                    'use_environment_credentials' => true,
                ]);
            });

            Notification::make()
                ->title('Learner onboarded successfully')
                ->success()
                ->send();

            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to onboard learner')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
