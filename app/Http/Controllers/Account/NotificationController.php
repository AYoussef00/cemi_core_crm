<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Ui\NotificationCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->paginate(20)
            ->withQueryString();

        return view('account.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Lightweight feed for the floating alert room (newest first).
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = (string) config('app.timezone');

        $items = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (DatabaseNotification $n) use ($tz) {
                $data = $n->data ?? [];

                return [
                    'id' => $n->id,
                    'title_ar' => (string) ($data['title_ar'] ?? 'إشعار'),
                    'body_ar' => $data['body_ar'] ?? null,
                    'action_url' => $this->safeActionUrl($data['action_url'] ?? null),
                    'category' => NotificationCategory::labelAr($data['category'] ?? null),
                    'unread' => $n->read_at === null,
                    'created_at' => $n->created_at?->timezone($tz)->toIso8601String(),
                    'created_label' => $n->created_at?->timezone($tz)->format('Y-m-d H:i'),
                ];
            })
            ->values();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'latest_id' => $items->first()['id'] ?? null,
            'items' => $items,
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $record = $this->ownedNotification($request, $notification);
        $record->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $record->id]);
        }

        return back()->with('status_ar', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status_ar', 'تم تعليم كل الإشعارات كمقروءة.');
    }

    private function safeActionUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return null;
    }

    private function ownedNotification(Request $request, string $id): DatabaseNotification
    {
        // Scope to the authenticated user — guessing another user's UUID yields 404.
        return $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();
    }
}
