<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Environment;
use Illuminate\Http\Response;

class ValidationController extends Controller
{
    /**
     * Validate if a subdomain is available.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
        
        // Check if the subdomain is already in use
        $exists = Environment::where('primary_domain', 'LIKE', $subdomain . '.%')
            ->orWhere('additional_domains', 'LIKE', '%' . $subdomain . '.%')
            ->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'This subdomain is already taken.' : 'Subdomain is available.',
        ]);
    }

    /**
     * Validate if a custom domain is available.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
            ->orWhere('additional_domains', 'LIKE', '%' . $domain . '%')
            ->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'This domain is already in use.' : 'Domain is available.',
        ]);
    }

    /**
     * Validate if an email is available (not already registered).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
            'available' => !$exists,
            'message' => $exists ? 'This email is already registered.' : 'Email is available.',
        ]);
    }
}
