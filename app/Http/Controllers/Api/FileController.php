<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
     * Batch store multiple files.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function batchStore(Request $request): JsonResponse
    {
        // Ensure the request contains an array
        if (!$request->isJson() || !is_array($request->json()->all())) {
            return response()->json(['error' => 'Request must be a JSON array of file metadata'], 400);
        }
        
        $requestData = $request->json()->all();
        
        // Filter out any non-file data (like environment objects) and ensure we have a proper array
        $files = [];
        foreach ($requestData as $key => $item) {
            // Skip if it's not an array/object or if it's the environment key
            if (!is_array($item) || $key === 'environment') {
                continue;
            }
            
            // Check if this looks like file metadata (has required file fields)
            if (isset($item['title']) && isset($item['file_url']) && isset($item['public_id'])) {
                $files[] = $item;
            }
        }
        
        // If no files found, check if the entire request is already a proper array of files
        if (empty($files) && is_array($requestData)) {
            // Check if the first element looks like file metadata
            $firstItem = reset($requestData);
            if (is_array($firstItem) && isset($firstItem['title']) && isset($firstItem['file_url'])) {
                $files = $requestData;
            }
        }
        
        if (empty($files)) {
            return response()->json(['error' => 'No valid file metadata found in request'], 400);
        }
        
        // Validate each file in the array
        $validator = Validator::make(['files' => $files], [
            'files' => 'required|array|min:1',
            'files.*.title' => 'required|string|max:255',
            'files.*.description' => 'nullable|string',
            'files.*.file_type' => 'required|string|max:100',
            'files.*.file_size' => 'required|integer|min:0',
            'files.*.file_url' => 'required|url|max:2048',
            'files.*.public_id' => 'required|string|max:255',
            'files.*.resource_type' => 'required|string|max:50',
            'files.*.environment_id' => 'required|integer|exists:environments,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        DB::transaction(function () use ($files) {
            foreach ($files as $fileData) {
                File::create($fileData);
            }
        });
        
        return response()->json(['message' => 'Files created successfully'], 201);
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
