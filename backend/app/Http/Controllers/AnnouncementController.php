<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    /**
     * Read-only: announcements are published by coordinators/PALD.
     */
    public function index(): JsonResponse
    {
        $announcements = Announcement::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->get();

        return response()->json(['announcements' => $announcements]);
    }
}
