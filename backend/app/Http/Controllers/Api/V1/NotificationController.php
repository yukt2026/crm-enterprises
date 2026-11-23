<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\Identity\Models\Notification;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get paginated notifications for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Notifications retrieved.',
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => Notification::where('user_id', $user->id)->unread()->count(),
            ],
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();
        $count = Notification::where('user_id', $user->id)->unread()->count();

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Unread notification count.',
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $user = Auth::user();
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read for current user
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        Notification::where('user_id', $user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Get user's notification preferences
     */
    public function preferences(): JsonResponse
    {
        $user = Auth::user();
        $prefs = $this->notificationService->getUserPreferences($user);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Notification preferences retrieved.',
            'data' => $prefs,
        ]);
    }

    /**
     * Update user's notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.notification_type' => 'required|string',
            'preferences.*.in_app_enabled' => 'boolean',
            'preferences.*.email_enabled' => 'boolean',
            'preferences.*.push_enabled' => 'boolean',
        ]);

        $user = Auth::user();
        $this->notificationService->savePreferences($user, $request->input('preferences'));

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Notification preferences updated.',
            'data' => $this->notificationService->getUserPreferences($user),
        ]);
    }

    /**
     * Dispatch test notification
     */
    public function dispatchTest(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string',
            'title' => 'required|string',
            'message' => 'required|string',
        ]);

        $user = Auth::user();
        $notif = $this->notificationService->notify(
            $user,
            $request->input('type', 'general'),
            $request->input('title'),
            $request->input('message'),
            $request->input('action_url')
        );

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'Test notification dispatched.',
            'data' => $notif,
        ], 201);
    }
}
