<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /api/v1/notifications — Fetch current user's notifications */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $unread = $notifications->whereNull('read_at')->count();

        return response()->json(
            ApiResponse::list($notifications)->getData(true) + ['unread_count' => $unread]
        );
    }

    /** POST /api/v1/notifications/mark-read — Mark all as read */
    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /** POST /api/v1/notifications/{id}/read — Mark single as read */
    public function markRead(Request $request, int $id)
    {
        $notif = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }
}
