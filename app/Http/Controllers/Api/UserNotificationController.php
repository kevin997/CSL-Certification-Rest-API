<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user in the current environment.
     *
     * @param Request $request
     * @param int $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, int $environmentId)
    {
        $perPage = $request->input('per_page', 15);
        $userId = Auth::id();
        
        $notifications = UserNotification::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }
    
    /**
     * Get unread notifications count for the authenticated user in the current environment.
     *
     * @param int $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(int $environmentId)
    {
        $userId = Auth::id();
        
        $count = UserNotification::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $count
            ]
        ]);
    }
    
    /**
     * Mark a notification as read.
     *
     * @param int $environmentId
     * @param int $notificationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(int $environmentId, int $notificationId)
    {
        $userId = Auth::id();
        
        $notification = UserNotification::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->where('id', $notificationId)
            ->first();
            
        if (!$notification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found'
            ], 404);
        }
        
        $notification->markAsRead();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read'
        ]);
    }
    
    /**
     * Mark all notifications as read for the authenticated user in the current environment.
     *
     * @param int $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(int $environmentId)
    {
        $userId = Auth::id();
        
        UserNotification::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
            
        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read'
        ]);
    }
}
