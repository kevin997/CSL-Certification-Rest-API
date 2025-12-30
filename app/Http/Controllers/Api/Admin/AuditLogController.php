<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = AuditLog::query();

        // Optional: Filter by environment if provided
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->input('environment_id'));
        }

        // Optional: Filter by log type
        if ($request->has('log_type')) {
            $query->where('log_type', $request->input('log_type'));
        }

        // Optional: Filter by source
        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $auditLog = AuditLog::with('user')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $auditLog,
        ]);
    }
}
