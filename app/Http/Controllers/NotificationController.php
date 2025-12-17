<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function getNotifications(Request $request)
    {
        $user = $request->get('auth_user');

        try {
            $notifications = DB::table('notifications')
                ->where('user_id', $user['id'])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            
            return response()->json(['success' => true, 'notifications' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch notifications: ' . $e->getMessage()], 500);
        }
    }

    public function markRead(Request $request)
    {
        $user = $request->get('auth_user');
        $data = $request->json()->all();
        $notificationId = $data['notification_id'] ?? 0;

        try {
            if ($notificationId > 0) {
                DB::table('notifications')
                    ->where('id', $notificationId)
                    ->where('user_id', $user['id'])
                    ->update(['read_at' => now()]);
            } else {
                DB::table('notifications')
                    ->where('user_id', $user['id'])
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
            
            return response()->json(['success' => true, 'message' => 'Notification marked as read']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update notification: ' . $e->getMessage()], 500);
        }
    }
}
