<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SessionAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'environment_id' => 'nullable|exists:environments,id',
        ]);

        $environmentId = $request->integer('environment_id');
        $user = null;
        $authenticatedViaEnvironment = false;
        $environmentUser = null;

        if ($request->has('environment_id')) {
            session(['current_environment_id' => $environmentId]);
        }

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            Log::info('Session Login: User authenticated via global password', ['user_id' => $user?->id]);
        }

        if (!$user && $environmentId) {
            $result = $this->tryEnvironmentCredentials($request->email, $request->password, $environmentId);

            if ($result) {
                $user = $result['user'];
                $environmentUser = $result['environmentUser'];
                $authenticatedViaEnvironment = true;

                $this->autoHealPassword($user, $request->password);
            }
        }

        if (!$user) {
            $result = $this->tryAnyEnvironmentCredentials($request->email, $request->password);

            if ($result) {
                $user = $result['user'];
                $environmentUser = $result['environmentUser'];
                $authenticatedViaEnvironment = true;

                $this->autoHealPassword($user, $request->password);

                // If no environment_id explicitly provided, persist the matched environment.
                session(['current_environment_id' => $environmentUser->environment_id]);
                $environmentId = (int) $environmentUser->environment_id;
            }
        }

        if (!$user) {
            throw ValidationException::withMessages([
                'credentials' => ['Invalid credentials provided.'],
            ]);
        }

        if ($authenticatedViaEnvironment) {
            Auth::login($user);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($environmentId) {
            $environment = Environment::find($environmentId);

            if (!$environment) {
                throw ValidationException::withMessages([
                    'credentials' => ['Invalid credentials provided.'],
                ]);
            }

            $isOwner = $environment->owner_id === $user->id;

            if (!$isOwner && !$environmentUser) {
                $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$environmentUser) {
                    throw ValidationException::withMessages([
                        'credentials' => ['Invalid credentials provided.'],
                    ]);
                }
            }

            $userRole = $user->role;
            $environmentRole = $environmentUser ? $environmentUser->role : null;

            $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;
            $environmentRoleValue = $environmentRole instanceof UserRole ? $environmentRole->value : $environmentRole;

            if ($isOwner) {
                $role = $userRoleValue === UserRole::COMPANY_TEACHER->value ? 'company_teacher' : 'individual_teacher';
            } else {
                $role = $environmentRoleValue ?: $userRoleValue;
            }
        } else {
            $userRoleValue = $user->role instanceof UserRole ? $user->role->value : $user->role;
            $role = $userRoleValue ?: 'user';
            $environmentRoleValue = null;
        }

        $isAccountSetup = null;
        if ($environmentId) {
            $envUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();
            $isAccountSetup = $envUser ? $envUser->is_account_setup : null;
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'environment_id' => $environmentId,
            'role' => $role,
            'user_role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
            'environment_role' => $environmentRoleValue,
            'is_account_setup' => $isAccountSetup,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    private function tryEnvironmentCredentials(string $email, string $password, int $environmentId): ?array
    {
        $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
            ->where('environment_email', $email)
            ->where('use_environment_credentials', true)
            ->first();

        if (!$environmentUser) {
            return null;
        }

        if (!Hash::check($password, $environmentUser->environment_password)) {
            return null;
        }

        $user = User::find($environmentUser->user_id);
        if (!$user) {
            return null;
        }

        Log::info('Session Login: User authenticated via environment credentials', [
            'user_id' => $user->id,
            'environment_id' => $environmentId,
        ]);

        return [
            'user' => $user,
            'environmentUser' => $environmentUser,
        ];
    }

    private function tryAnyEnvironmentCredentials(string $email, string $password): ?array
    {
        $environmentUsers = EnvironmentUser::where('environment_email', $email)
            ->where('use_environment_credentials', true)
            ->get();

        foreach ($environmentUsers as $environmentUser) {
            if (Hash::check($password, $environmentUser->environment_password)) {
                $user = User::find($environmentUser->user_id);
                if ($user) {
                    Log::info('Session Login: User authenticated via any environment credentials', [
                        'user_id' => $user->id,
                        'environment_id' => $environmentUser->environment_id,
                    ]);

                    return [
                        'user' => $user,
                        'environmentUser' => $environmentUser,
                    ];
                }
            }
        }

        return null;
    }

    private function autoHealPassword(User $user, string $plainPassword): void
    {
        try {
            $user->password = Hash::make($plainPassword);
            $user->save();

            Log::info('Session Login: Auto-healed password to users table', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Session Login: Failed to auto-heal password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
