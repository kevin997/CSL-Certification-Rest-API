<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Course;
use App\Enums\UserRole;

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

// Course discussion presence channel
Broadcast::channel('course.{courseId}.discussion', function (User $user, string $courseId) {
    // Check if user is enrolled in the course or is an instructor
    $course = Course::find($courseId);

    if (!$course) {
        return false;
    }

    // Check enrollment
    $isEnrolled = $course->enrolledUsers()->where('users.id', $user->id)->exists();

    // Check if user is an instructor (teacher role)
    $isInstructor = $user->isTeacher();

    if ($isEnrolled || $isInstructor) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar_url ?? null,
            'role' => $isInstructor ? 'instructor' : 'student'
        ];
    }

    return false;
});

// Private chat channel
Broadcast::channel('user.{userId}.private', function (User $user, string $userId) {
    return (int) $user->id === (int) $userId;
});
