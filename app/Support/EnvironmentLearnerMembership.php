<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;

final class EnvironmentLearnerMembership
{
    public function join(User $user, Environment $environment): EnvironmentUser
    {
        return EnvironmentUser::query()->firstOrCreate(
            [
                'environment_id' => $environment->id,
                'user_id' => $user->id,
            ],
            [
                'role' => UserRole::LEARNER->value,
                'permissions' => [],
                'joined_at' => now(),
                'use_environment_credentials' => false,
                'is_account_setup' => true,
            ],
        );
    }
}
