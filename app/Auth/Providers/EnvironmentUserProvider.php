<?php

namespace App\Auth\Providers;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EnvironmentUserProvider extends EloquentUserProvider
{
    /**
     * The current environment ID.
     *
     * @var int|null
     */
    protected $environmentId;

    /**
     * Create a new environment user provider.
     *
     * @param  \Illuminate\Contracts\Hashing\Hasher  $hasher
     * @param  string  $model
     * @param  int|null  $environmentId
     * @return void
     */
    public function __construct(HasherContract $hasher, $model, $environmentId = null)
    {
        parent::__construct($hasher, $model);
        $this->environmentId = $environmentId ?? session('current_environment_id');
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) || !isset($credentials['email']) || !$this->environmentId) {
            return parent::retrieveByCredentials($credentials);
        }

        // First, try to find a user with environment-specific credentials
        $pivot = DB::table('environment_user')
            ->where('environment_id', $this->environmentId)
            ->where('environment_email', $credentials['email'])
            ->where('use_environment_credentials', true)
            ->first();

        if ($pivot) {
            return User::find($pivot->user_id);
        }

        // If no environment-specific user found, fall back to regular authentication
        return parent::retrieveByCredentials($credentials);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (!$this->environmentId) {
            return parent::validateCredentials($user, $credentials);
        }

        // Check if user has environment-specific credentials
        $pivot = DB::table('environment_user')
            ->where('environment_id', $this->environmentId)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('use_environment_credentials', true)
            ->first();

        if ($pivot && isset($credentials['password'])) {
            return Hash::check($credentials['password'], $pivot->environment_password);
        }

        // Fall back to regular authentication
        return parent::validateCredentials($user, $credentials);
    }

    /**
     * Set the current environment ID.
     *
     * @param  int  $environmentId
     * @return $this
     */
    public function setEnvironmentId($environmentId)
    {
        $this->environmentId = $environmentId;
        return $this;
    }
}
