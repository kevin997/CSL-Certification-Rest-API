<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Environment-scoped notifications channel (environment-wide)
Broadcast::channel('env.{envId}.notifications', function ($user, $envId) {
    $envId = (int) $envId;
    // Bypass any model global scopes by querying pivot and environments tables directly
    $isMember = DB::table('environment_user')
        ->where('user_id', $user->id)
        ->where('environment_id', $envId)
        ->exists();

    $isOwner = DB::table('environments')
        ->where('id', $envId)
        ->where('owner_id', $user->id)
        ->exists();

    return $isMember || $isOwner;
});

// Environment-scoped per-user channel
Broadcast::channel('env.{envId}.users.{userId}', function ($user, $envId, $userId) {
    $envId = (int) $envId;
    $userId = (int) $userId;

    if ((int) $user->id !== $userId) {
        return false;
    }

    $isMember = DB::table('environment_user')
        ->where('user_id', $user->id)
        ->where('environment_id', $envId)
        ->exists();

    $isOwner = DB::table('environments')
        ->where('id', $envId)
        ->where('owner_id', $user->id)
        ->exists();

    return $isMember || $isOwner;
});
