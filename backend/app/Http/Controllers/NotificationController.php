<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserNotification::query()->where('user_id', $request->user()->id)->orderByDesc('created_at')->limit(100)->get();
        return response()->json(['data' => $items, 'unread_count' => $items->whereNull('read_at')->count()]);
    }

    public function read(Request $request, UserNotification $userNotification): JsonResponse
    {
        abort_unless((int) $userNotification->user_id === (int) $request->user()->id, 404);
        $userNotification->update(['read_at' => now()]);
        return response()->json(['data' => $userNotification->fresh()]);
    }
}
