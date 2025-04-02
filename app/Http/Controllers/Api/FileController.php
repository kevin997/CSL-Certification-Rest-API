<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    /**
     * Get all files for an environment.
     *
     * @param int $environmentId
     * @return JsonResponse
     */
    public function getByEnvironment(int $environmentId): JsonResponse
    {
        $environment = Environment::find($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $files = File::where('environment_id', $environmentId)->get();
        
        return response()->json($files);
    }
    
    /**
     * Store a new file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file_type' => 'required|string|max:100',
            'file_size' => 'required|integer',
            'file_url' => 'required|url|max:2048',
            'public_id' => 'required|string|max:255',
            'resource_type' => 'required|string|max:50',
            'environment_id' => 'required|integer|exists:environments,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $file = File::create($request->all());
        
        return response()->json($file, 201);
    }
    
    /**
     * Get a specific file.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $file = File::find($id);
        
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        return response()->json($file);
    }
    
    /**
     * Update a file.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $file = File::find($id);
        
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $file->update($request->only(['title', 'description']));
        
        return response()->json($file);
    }
    
    /**
     * Delete a file.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $file = File::find($id);
        
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        $file->delete();
        
        return response()->json(['message' => 'File deleted successfully']);
    }
}
