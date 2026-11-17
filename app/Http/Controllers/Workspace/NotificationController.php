<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspace.notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = ['unread' => $user->unreadNotifications()->count()];
        if ($request->boolean('preview')) {
            $payload['preview'] = view('workspace.notification-preview', [
                'latestNotices' => $user->notifications()->latest()->limit(5)->get(),
            ])->render();
        }

        return response()->json($payload);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? '';

        return str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')
            ? redirect($url)
            : redirect()->route('workspace.notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
