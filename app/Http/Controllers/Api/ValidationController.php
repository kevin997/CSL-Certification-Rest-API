<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\User;
use App\Support\Tenancy\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ValidationController extends Controller
{
    /**
     * Validate if a subdomain is available.
     *
     * @return Response
     */
    public function validateSubdomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdomain' => 'required|string|max:255|regex:/^[a-z0-9-]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid subdomain format. Use only lowercase letters, numbers, and hyphens.',
                'errors' => $validator->errors(),
            ], Response::HTTP_OK); // Return 200 even for validation errors
        }

        $subdomain = $request->subdomain;

        try {
            $host = TenantDomain::compose('subdomain', $subdomain);
        } catch (\RuntimeException $e) {
            return response()->json(['available' => false, 'message' => $e->getMessage()], Response::HTTP_OK);
        }

        $exists = Environment::where('primary_domain', $host)
            ->orWhereJsonContains('additional_domains', $host)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'This subdomain is already taken.' : 'Subdomain is available.',
        ]);
    }

    /**
     * Validate if a custom domain is available.
     *
     * @return Response
     */
    public function validateDomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255|regex:/^[a-z0-9][a-z0-9-]*(\.[a-z0-9][a-z0-9-]*)+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid domain format. Please enter a valid domain (e.g., yourdomain.com).',
                'errors' => $validator->errors(),
            ], Response::HTTP_OK); // Return 200 even for validation errors
        }

        $domain = $request->domain;

        // Check if the domain is already in use
        $exists = Environment::where('primary_domain', $domain)
            ->orWhereJsonContains('additional_domains', $domain)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'This domain is already in use.' : 'Domain is available.',
        ]);
    }

    /**
     * Validate if an email is available (not already registered).
     *
     * @return Response
     */
    public function validateEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid email format.',
                'errors' => $validator->errors(),
            ], Response::HTTP_OK); // Return 200 even for validation errors
        }

        $email = $request->email;

        // Check if the email is already registered
        $exists = User::where('email', $email)->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'This email is already registered.' : 'Email is available.',
        ]);
    }
}
