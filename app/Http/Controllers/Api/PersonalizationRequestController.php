<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalizationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PersonalizationRequestController extends Controller
{
    /**
     * Store a newly created personalization request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'whatsapp_number' => 'required|string|max:255',
            'academy_name' => 'required|string|max:255',
            'description' => 'required|string',
            'organization_type' => 'nullable|string',
            'niche' => 'nullable|string',
        ]);

        $personalizationRequest = PersonalizationRequest::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Personalization request submitted successfully',
            'data' => $personalizationRequest,
        ], 201);
    }

    /**
     * Update the specified personalization request.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $personalizationRequest = PersonalizationRequest::findOrFail($id);

        $validatedData = $request->validate([
            'status' => 'required|string|in:pending,processed',
        ]);

        $personalizationRequest->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Personalization request updated successfully',
            'data' => $personalizationRequest,
        ]);
    }

    /**
     * Display a listing of the personalization requests.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $requests = PersonalizationRequest::orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }
}
