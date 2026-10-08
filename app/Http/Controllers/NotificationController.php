<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in member's notification center for the current organization.
 * Notifications are always looked up through the member's own inbox, so one
 * person can never read or change another's.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function index(Request $request): Response
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = $this->inbox($request)
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Notification $notification): array => $notification->toCenterArray());

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $this->inbox($request)->whereNull('read_at')->count(),
        ]);
    }

    /**
     * The newest few, for the header popover.
     */
    public function recent(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $this->inbox($request)->whereNull('read_at')->count(),
            'items' => $this->inbox($request)
                ->limit(8)
                ->get()
                ->map(fn (Notification $notification): array => $notification->toCenterArray()),
        ]);
    }

    /**
     * Mark one notification read and go to what it is about.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->inbox($request)->findOrFail($notification);
        $record->markAsRead();

        return redirect()->to($this->safeUrl($record->data['url'] ?? null));
    }

    public function markRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $this->inbox($request)->findOrFail($notification)->markAsRead();

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['ok' => true])
            : back();
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $this->inbox($request)->whereNull('read_at')->update(['read_at' => now()]);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['ok' => true])
            : back();
    }

    /**
     * @return MorphMany<Notification, User>
     */
    private function inbox(Request $request): MorphMany
    {
        /** @var User $user */
        $user = $request->user();

        return $user->notifications()->where('organization_id', $this->tenancy->currentOrFail()->id);
    }

    /**
     * Only follow links back into this application; anything else lands on the inbox.
     */
    private function safeUrl(mixed $url): string
    {
        $fallback = route('notifications.index');

        if (! is_string($url) || $url === '') {
            return $fallback;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);

        if ($targetHost === null && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return $targetHost === $appHost || $targetHost === request()->getHost() ? $url : $fallback;
    }
}
