<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="EnvironmentRequest",
 *     title="Environment Request",
 *     description="Environment request model",
 *     required={"name", "primary_domain"},
 *     @OA\Property(property="name", type="string", example="Acme Corp Training"),
 *     @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
 *     @OA\Property(property="additional_domains", type="array", @OA\Items(type="string"), example={"learn.acmecorp.com", "edu.acmecorp.com"}),
 *     @OA\Property(property="theme_color", type="string", example="#4F46E5"),
 *     @OA\Property(property="logo_url", type="string", example="https://acmecorp.com/logo.png"),
 *     @OA\Property(property="favicon_url", type="string", example="https://acmecorp.com/favicon.ico"),
 *     @OA\Property(property="description", type="string", example="Corporate training environment for Acme Corp employees")
 * )
 */

/**
 * @OA\Tag(
 *     name="Environments",
 *     description="API Endpoints for managing environments"
 * )
 */
class EnvironmentController extends Controller
{
    /**
     * Check if user has administrative privileges.
     * Allows: teachers, admins, super admins, sales agents.
     */
    private function userHasAdminPrivileges($user): bool
    {
        return $user->isTeacher() || $user->isAdmin() || $user->isSalesAgent();
    }

    /**
     * Check if user can manage a specific environment.
     * Allows: admins, or the environment owner.
     */
    private function userCanManageEnvironment($user, Environment $environment): bool
    {
        return $this->userHasAdminPrivileges($user) || $environment->owner_id === $user->id;
    }

    /**
     * @OA\Get(
     *     path="/api/environments",
     *     summary="Get all environments for the authenticated user",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of environments",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64", example=1),
     *                 @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *                 @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *                 @OA\Property(property="additional_domains", type="string", nullable=true, example="learn.acmecorp.com,edu.acmecorp.com"),
     *                 @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *                 @OA\Property(property="logo_url", type="string", nullable=true, example="https://acmecorp.com/logo.png"),
     *                 @OA\Property(property="favicon_url", type="string", nullable=true, example="https://acmecorp.com/favicon.ico"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="owner_id", type="integer", format="int64", example=1),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Corporate training environment for Acme Corp employees"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // If user has admin privileges, return all environments
        if ($this->userHasAdminPrivileges($request->user())) {
            return Environment::all();
        }
        
        // Otherwise return only environments owned by this user
        return $request->user()->ownedEnvironments;
    }

    /**
     * @OA\Post(
     *     path="/api/environments",
     *     summary="Create a new environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "primary_domain"},
     *             @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *             @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *             @OA\Property(property="additional_domains", type="array", @OA\Items(type="string"), example={"learn.acmecorp.com", "edu.acmecorp.com"}),
     *             @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *             @OA\Property(property="logo_url", type="string", example="https://acmecorp.com/logo.png"),
     *             @OA\Property(property="favicon_url", type="string", example="https://acmecorp.com/favicon.ico"),
     *             @OA\Property(property="description", type="string", example="Corporate training environment for Acme Corp employees")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Environment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", format="int64", example=1),
     *             @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *             @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *             @OA\Property(property="additional_domains", type="string", nullable=true, example="learn.acmecorp.com,edu.acmecorp.com"),
     *             @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *             @OA\Property(property="logo_url", type="string", nullable=true, example="https://acmecorp.com/logo.png"),
     *             @OA\Property(property="favicon_url", type="string", nullable=true, example="https://acmecorp.com/favicon.ico"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="owner_id", type="integer", format="int64", example=1),
     *             @OA\Property(property="description", type="string", nullable=true, example="Corporate training environment for Acme Corp employees"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'primary_domain' => 'required|string|max:255|unique:environments,primary_domain',
            'additional_domains' => 'nullable|array',
            'additional_domains.*' => 'string|max:255',
            'theme_color' => 'nullable|string|max:7',
            'logo_url' => 'nullable|string|max:255',
            'favicon_url' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the environment
        $environment = new Environment($request->all());
        $environment->owner_id = $request->user()->id;
        $environment->save();

        return response()->json($environment, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/environments/{id}",
     *     summary="Get a specific environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Environment details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", format="int64", example=1),
     *             @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *             @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *             @OA\Property(property="additional_domains", type="string", nullable=true, example="learn.acmecorp.com,edu.acmecorp.com"),
     *             @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *             @OA\Property(property="logo_url", type="string", nullable=true, example="https://acmecorp.com/logo.png"),
     *             @OA\Property(property="favicon_url", type="string", nullable=true, example="https://acmecorp.com/favicon.ico"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="owner_id", type="integer", format="int64", example=1),
     *             @OA\Property(property="description", type="string", nullable=true, example="Corporate training environment for Acme Corp employees"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found"
     *     )
     * )
     */
    public function show($id, Request $request)
    {
        $environment = Environment::findOrFail($id);
        
        // Check if user has permission to view this environment
        if (!$this->userCanManageEnvironment($request->user(), $environment)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return $environment;
    }

    /**
     * @OA\Put(
     *     path="/api/environments/{id}",
     *     summary="Update an environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "primary_domain"},
     *             @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *             @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *             @OA\Property(property="additional_domains", type="array", @OA\Items(type="string"), example={"learn.acmecorp.com", "edu.acmecorp.com"}),
     *             @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *             @OA\Property(property="logo_url", type="string", example="https://acmecorp.com/logo.png"),
     *             @OA\Property(property="favicon_url", type="string", example="https://acmecorp.com/favicon.ico"),
     *             @OA\Property(property="description", type="string", example="Corporate training environment for Acme Corp employees")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Environment updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", format="int64", example=1),
     *             @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *             @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *             @OA\Property(property="additional_domains", type="string", nullable=true, example="learn.acmecorp.com,edu.acmecorp.com"),
     *             @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *             @OA\Property(property="logo_url", type="string", nullable=true, example="https://acmecorp.com/logo.png"),
     *             @OA\Property(property="favicon_url", type="string", nullable=true, example="https://acmecorp.com/favicon.ico"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="owner_id", type="integer", format="int64", example=1),
     *             @OA\Property(property="description", type="string", nullable=true, example="Corporate training environment for Acme Corp employees"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update($id, Request $request)
    {
        $environment = Environment::findOrFail($id);
        
        // Check if user has permission to update this environment
        if (!$this->userCanManageEnvironment($request->user(), $environment)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Validate the request with limited fields
        $validationRules = [
            'name' => 'sometimes|required|string|max:255',
            'additional_domains' => 'nullable|array',
            'additional_domains.*' => 'string|max:255',
            'description' => 'nullable|string',
        ];

        // Allow admins to update primary_domain
        if ($this->userHasAdminPrivileges($request->user())) {
            $validationRules['primary_domain'] = [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('environments', 'primary_domain')->ignore($environment->id)
            ];
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update environment with limited fields
        // Allow admins to update primary_domain
        $fillableFields = [
            'name',
            'additional_domains',
            'description',
        ];

        if ($this->userHasAdminPrivileges($request->user()) && $request->has('primary_domain')) {
            $fillableFields[] = 'primary_domain';
        }

        $environment->fill($request->only($fillableFields));
        $environment->save();
        
        return $environment;
    }

    /**
     * @OA\Delete(
     *     path="/api/environments/{id}",
     *     summary="Delete an environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Environment deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found"
     *     )
     * )
     */
    public function destroy($id, Request $request)
    {
        $environment = Environment::findOrFail($id);
        
        // Check if user has permission to delete this environment
        if (!$this->userCanManageEnvironment($request->user(), $environment)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $environment->delete();
        
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/current-environment",
     *     summary="Get the current environment based on domain",
     *     tags={"Environments"},
     *     @OA\Response(
     *         response=200,
     *         description="Current environment details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", format="int64", example=1),
     *             @OA\Property(property="name", type="string", example="Acme Corp Training"),
     *             @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
     *             @OA\Property(property="additional_domains", type="string", nullable=true, example="learn.acmecorp.com,edu.acmecorp.com"),
     *             @OA\Property(property="theme_color", type="string", example="#4F46E5"),
     *             @OA\Property(property="logo_url", type="string", nullable=true, example="https://acmecorp.com/logo.png"),
     *             @OA\Property(property="favicon_url", type="string", nullable=true, example="https://acmecorp.com/favicon.ico"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="owner_id", type="integer", format="int64", example=1),
     *             @OA\Property(property="description", type="string", nullable=true, example="Corporate training environment for Acme Corp employees"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No environment found for this domain"
     *     )
     * )
     */
    public function getCurrentEnvironment(Request $request)
    {
        if (!$request->has('environment')) {
            return response()->json(['error' => 'No environment found for this domain'], 404);
        }
        
        return $request->get('environment');
    }

    /**
     * @OA\Get(
     *     path="/api/environments/{id}/users",
     *     summary="Get all users in an environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of users in the environment",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found"
     *     )
     * )
     */
    public function getUsers($id, Request $request)
    {
        $environment = Environment::findOrFail($id);
        
        // Check if user has permission to view this environment's users
        if (!$this->userCanManageEnvironment($request->user(), $environment)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return $environment->users()->with('profile')->get();
    }

    /**
     * @OA\Post(
     *     path="/api/environments/{id}/users",
     *     summary="Add a user to an environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="role", type="string", example="instructor"),
     *             @OA\Property(property="environment_email", type="string", example="test.example@csl.com"),
     *             @OA\Property(property="environment_password", type="string", example="Passowrd123!"),
     *             @OA\Property(property="use_environment_credentials", type="boolean", example="true"),
     *             @OA\Property(property="permissions", type="object", example={"create_course": true, "manage_users": false})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User added to environment successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment or user not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function addUser($id, Request $request)
    {
        $environment = Environment::findOrFail($id);
        
        // Check if user has permission to add users to this environment
        if (!$this->userCanManageEnvironment($request->user(), $environment)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|max:255',
            'permissions' => 'nullable|json',
            'environment_email' => 'nullable|email',
            'environment_password' => 'nullable|string',
            'use_environment_credentials' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the user is already in the environment
        $existingAssociation = $environment->users()
            ->where('user_id', $request->user_id)
            ->exists();
            
        if ($existingAssociation) {
            // Update the existing association
            $pivotData = [
                'role' => $request->role,
                'permissions' => $request->permissions,
            ];
            
            // Add environment-specific credentials if provided
            if ($request->has('use_environment_credentials')) {
                $pivotData['use_environment_credentials'] = $request->use_environment_credentials;
            }
            
            if ($request->has('environment_email')) {
                $pivotData['environment_email'] = $request->environment_email;
            }
            
            if ($request->has('environment_password') && $request->environment_password) {
                $pivotData['environment_password'] = Hash::make($request->environment_password);
            }
            
            $environment->users()->updateExistingPivot($request->user_id, $pivotData);
            
            return response()->json(['message' => 'User association updated successfully']);
        } else {
            // Create a new association
            $pivotData = [
                'role' => $request->role,
                'permissions' => $request->permissions,
                'joined_at' => now(),
            ];
            
            // Add environment-specific credentials if provided
            if ($request->has('use_environment_credentials')) {
                $pivotData['use_environment_credentials'] = $request->use_environment_credentials;
            }
            
            if ($request->has('environment_email')) {
                $pivotData['environment_email'] = $request->environment_email;
            }
            
            if ($request->has('environment_password') && $request->environment_password) {
                $pivotData['environment_password'] = Hash::make($request->environment_password);
            }
            
            $environment->users()->attach($request->user_id, $pivotData);
            
            return response()->json(['message' => 'User added to environment successfully']);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/environments/{id}/users/{userId}",
     *     summary="Remove a user from an environment",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User removed from environment successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment or user not found"
     *     )
     * )
     */
    public function removeUser($id, $userId, Request $request)
    {
        $environment = Environment::findOrFail($id);
        
        // Check if user has permission to remove users from this environment
        if (!$this->userCanManageEnvironment($request->user(), $environment)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Cannot remove the owner
        if ($environment->owner_id == $userId) {
            return response()->json(['error' => 'Cannot remove the environment owner'], 422);
        }
        
        // Remove the user from the environment
        $environment->users()->detach($userId);
        
        return response()->json(['message' => 'User removed from environment successfully']);
    }

    /**
     * Get environment status (demo plan detection)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            // Get current environment based on domain or user context
            $environment = null;

            // Try to get environment from domain first
            // Try to get domain from headers in priority order
            $domain = null;

            // First check for the explicit X-Frontend-Domain header
            $frontendDomainHeader = $request->header('X-Frontend-Domain');

            // Then try Origin or Referer as fallbacks
            $origin = $request->header('Origin');
            $referer = $request->header('Referer');

            if ($frontendDomainHeader) {
                // Use the explicit frontend domain header if provided
                $domain = $frontendDomainHeader;
            } elseif ($origin) {
                // Extract domain from Origin
                $parsedOrigin = parse_url($origin);
                $domain = $parsedOrigin['host'] ?? null;
            } elseif ($referer) {
                // Extract domain from Referer as fallback
                $parsedReferer = parse_url($referer);
                $domain = $parsedReferer['host'] ?? null;
            }

            $environment = Environment::where('primary_domain', $domain)
                ->orWhere('additional_domains', 'like', "%{$domain}%")
                ->first();
            
            // If no environment found by domain, get user's first environment
            if (!$environment && $request->user()) {
                $environment = $request->user()->ownedEnvironments()->first() 
                    ?? $request->user()->environments()->first();
            }
            
            if (!$environment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No environment found'
                ], 404);
            }
            
            // Define domains that should be exempted from subscription provider enforcement
            // These are master/default environments used for testing
            $exemptedDomains = [
                'learning.csl-brands.com',
                'localhost:3000',
                'localhost'
            ];
            
            // Check if current domain is exempted
            $isDemoOverride = $environment->is_demo;
            if (in_array($domain, $exemptedDomains)) {
                // Force is_demo to false for exempted domains to bypass subscription enforcement
                $isDemoOverride = false;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $environment->id,
                    'name' => $environment->name,
                    'is_demo' => (bool) $isDemoOverride,
                    'is_active' => (bool) $environment->is_active,
                    'primary_domain' => $environment->primary_domain,
                    'theme_color' => $environment->theme_color,
                    'logo_url' => $environment->logo_url
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get environment status'
            ], 500);
        }
    }

    /**
     * Update environment demo status
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDemoStatus($id, Request $request)
    {
        try {
            $environment = Environment::findOrFail($id);
            
            // Check if user has admin permissions
            if (!$this->userHasAdminPrivileges($request->user())) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }
            
            // Validate request
            $request->validate([
                'is_demo' => 'required|boolean'
            ]);
            
            // Check if the status is actually changing
            $wasDemo = $environment->is_demo;
            $isDemo = $request->is_demo;
            
            if ($wasDemo === $isDemo) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Environment demo status unchanged',
                    'data' => [
                        'id' => $environment->id,
                        'name' => $environment->name,
                        'is_demo' => $environment->is_demo
                    ]
                ], 200);
            }
            
            // Use database transaction for atomicity
            return DB::transaction(function () use ($environment, $isDemo, $wasDemo) {
                // Update demo status
                $environment->is_demo = $isDemo;
                $environment->save();
                
                // Get the environment owner
                $owner = User::find($environment->owner_id);
                
                if (!$owner) {
                    throw new \Exception('Environment owner not found');
                }
                
                // Handle subscription changes
                if ($wasDemo && !$isDemo) {
                    // Promoting from demo to standalone
                    $this->promoteToStandalone($environment, $owner);
                    $subscriptionMessage = 'Subscription promoted to standalone plan.';
                } else {
                    // Demoting from standalone to demo (if needed)
                    $this->demoteToDemo($environment, $owner);
                    $subscriptionMessage = 'Subscription changed to demo trial.';
                }
                
                // Load the updated subscription for response
                $environment->load('subscription');
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Environment demo status updated successfully. ' . $subscriptionMessage,
                    'data' => [
                        'id' => $environment->id,
                        'name' => $environment->name,
                        'is_demo' => $environment->is_demo,
                        'subscription' => $environment->subscription ? [
                            'id' => $environment->subscription->id,
                            'plan_id' => $environment->subscription->plan_id,
                            'status' => $environment->subscription->status,
                            'starts_at' => $environment->subscription->starts_at,
                            'ends_at' => $environment->subscription->ends_at,
                        ] : null
                    ]
                ], 200);
            });
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update environment demo status', [
                'environment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update environment demo status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Promote an environment from demo to standalone plan.
     * Cancels any existing demo/trial subscription and creates an active standalone subscription.
     *
     * @param Environment $environment
     * @param User $owner
     * @return Subscription
     */
    private function promoteToStandalone(Environment $environment, User $owner): Subscription
    {
        // Get the standalone plan
        $standalonePlan = Plan::where('type', 'standalone')->firstOrFail();
        
        // Cancel any existing subscriptions for this environment
        Subscription::where('environment_id', $environment->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => now(),
            ]);
        
        // Create a new active standalone subscription
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'plan_id' => $standalonePlan->id,
            'environment_id' => $environment->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => null, // No end date for standalone (free) plan
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        
        Log::info('Environment promoted to standalone plan', [
            'environment_id' => $environment->id,
            'subscription_id' => $subscription->id,
            'owner_id' => $owner->id,
        ]);
        
        return $subscription;
    }

    /**
     * Demote an environment from standalone to demo/trial plan.
     * Cancels the standalone subscription and creates a new demo trial subscription.
     *
     * @param Environment $environment
     * @param User $owner
     * @return Subscription
     */
    private function demoteToDemo(Environment $environment, User $owner): Subscription
    {
        // Get the demo plan
        $demoPlan = Plan::where('type', 'demo')->firstOrFail();
        
        // Cancel any existing subscriptions for this environment
        Subscription::where('environment_id', $environment->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => now(),
            ]);
        
        // Create a new demo trial subscription (14 days from now)
        $expiresAt = \Carbon\Carbon::now()->addDays(14);
        
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'plan_id' => $demoPlan->id,
            'environment_id' => $environment->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => $expiresAt,
            'status' => Subscription::STATUS_TRIAL,
            'trial_ends_at' => $expiresAt,
        ]);
        
        Log::info('Environment demoted to demo trial', [
            'environment_id' => $environment->id,
            'subscription_id' => $subscription->id,
            'owner_id' => $owner->id,
            'expires_at' => $expiresAt,
        ]);
        
        return $subscription;
    }

    /**
     * Update the environment owner's password.
     * Only accessible by admins/sales agents.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Put(
     *     path="/api/environments/{id}/owner-password",
     *     summary="Update environment owner's password",
     *     tags={"Environments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", minLength=6, example="newSecurePassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Owner password updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Environment not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateOwnerPassword($id, Request $request)
    {
        try {
            $environment = Environment::findOrFail($id);
            
            // Check if user has admin permissions
            if (!$this->userHasAdminPrivileges($request->user())) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }
            
            // Validate request
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:6',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Get the environment owner
            $owner = User::find($environment->owner_id);
            
            if (!$owner) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Environment owner not found'
                ], 404);
            }
            
            // Update the owner's password
            $owner->password = Hash::make($request->password);
            $owner->save();
            
            Log::info('Environment owner password updated by admin', [
                'environment_id' => $environment->id,
                'owner_id' => $owner->id,
                'admin_id' => $request->user()->id,
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Owner password updated successfully'
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update environment owner password', [
                'environment_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update owner password'
            ], 500);
        }
    }

    public function publicIndex(Request $request)
    {
        // Use whereHas with a closure to disable the global EnvironmentScope
        // This ensures check for brandings works across ALL environments, ignoring the current session/domain context
        $query = Environment::where('is_active', 1)
            ->whereHas('brandings', function ($q) {
                $q->withoutGlobalScope(\App\Scopes\EnvironmentScope::class);
            })
            ->with(['branding' => function ($q) {
                $q->withoutGlobalScope(\App\Scopes\EnvironmentScope::class);
            }])
            ->orderBy('id', 'asc');

        $environments = $query->cursorPaginate($request->input('per_page', 10));
        
        return \App\Http\Resources\PublicEnvironmentResource::collection($environments);
    }
}

/**
 * @OA\Schema(
 *     schema="Environment",
 *     title="Environment",
 *     description="Environment model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="CSL Learning"),
 *     @OA\Property(property="primary_domain", type="string", example="learn.csl.com"),
 *     @OA\Property(property="additional_domains", type="array", @OA\Items(type="string"), example={"courses.csl.com", "training.csl.com"}),
 *     @OA\Property(property="theme_color", type="string", example="#FF5733"),
 *     @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
 *     @OA\Property(property="favicon_url", type="string", example="https://example.com/favicon.ico"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="owner_id", type="integer", example=1),
 *     @OA\Property(property="description", type="string", example="Main learning environment for CSL"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="User",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="profile", type="object", ref="#/components/schemas/Profile")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Profile",
 *     title="Profile",
 *     description="Profile model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="bio", type="string", example="This is my bio"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
