<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationInterest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * "Notify me" registrations for coming-soon integrations
 * (from the /settings/integrations catalog).
 */
class IntegrationInterestController extends Controller
{
    /**
     * The current user's registered interests (integration ids).
     */
    public function index(Request $request)
    {
        $interests = IntegrationInterest::where('user_id', $request->user()->id)
            ->pluck('integration_id')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $interests,
        ]);
    }

    /**
     * Register interest in an integration (idempotent).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'integration_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $interest = IntegrationInterest::firstOrCreate([
            'user_id' => $request->user()->id,
            'integration_id' => $request->input('integration_id'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Interest registered.',
            'data' => $interest,
        ], 201);
    }
}
