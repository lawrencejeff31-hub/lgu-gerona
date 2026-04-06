<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 20);
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($notifications);
    }

    public function unreadCount()
    {
        $user = Auth::user();
        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Marked as read',
            'notification' => $notification,
        ]);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->delete();
        return response()->json(['message' => 'Deleted']);
    }
}