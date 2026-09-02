<?php

namespace App\Support\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Once a user has set a real password, every academy they belong to may stop
 * asking. The password is global, so this marks all memberships at once.
 */
final class AccountSetupMarker
{
    public static function markAllMemberships(User $user): int
    {
        return DB::table('environment_user')
            ->where('user_id', $user->id)
            ->update([
                'is_account_setup' => true,
                'use_environment_credentials' => false,
            ]);
    }
}
