<?php

namespace App\Support\Notifications;

use App\Models\Role;
use App\Models\User;
use App\Notifications\InternalDatabaseNotification;
use App\Support\Audit\AttributeRedactor;
use App\Support\Authorization\RoleSlug;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reusable internal (in-app) notifications for future domain modules.
 * Delivers to the database channel only — no email/WhatsApp/push/broadcast.
 */
class InternalNotifier
{
    public function __construct(private AttributeRedactor $redactor) {}

    /**
     * Notify a single user.
     *
     * @param  array<string, mixed>  $payload  title_ar (required), body_ar, action_url, category, plus safe metadata
     * @return int Number of recipients notified
     */
    public function notifyUser(User $user, array $payload): int
    {
        if (! $user->is_active) {
            return 0;
        }

        $user->notify(new InternalDatabaseNotification($this->sanitize($payload)));

        return 1;
    }

    /**
     * Notify all active users holding the given role.
     *
     * @param  array<string, mixed>  $payload
     * @return int Number of recipients notified
     */
    public function notifyRole(RoleSlug|Role|string $role, array $payload): int
    {
        $roleId = $this->resolveRoleId($role);
        $safe = $this->sanitize($payload);

        $users = User::query()
            ->where('role_id', $roleId)
            ->where('is_active', true)
            ->get();

        return $this->notifyMany($users, $safe);
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     * @param  array<string, mixed>  $payload
     */
    public function notifyMany(iterable $users, array $payload): int
    {
        $safe = $this->sanitize($payload);
        $count = 0;

        foreach ($users as $user) {
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            $user->notify(new InternalDatabaseNotification($safe));
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title_ar: string, body_ar: ?string, action_url: ?string, category: ?string}
     */
    public function sanitize(array $payload): array
    {
        if (! filled($payload['title_ar'] ?? null)) {
            throw new InvalidArgumentException('عنوان الإشعار (title_ar) مطلوب.');
        }

        $clean = $this->redactor->redact($payload);

        $actionUrl = isset($clean['action_url']) ? $this->safeActionUrl((string) $clean['action_url']) : null;

        return [
            'title_ar' => Str::limit(strip_tags((string) $clean['title_ar']), 200, ''),
            'body_ar' => isset($clean['body_ar']) && filled($clean['body_ar'])
                ? Str::limit(strip_tags((string) $clean['body_ar']), 1000, '')
                : null,
            'action_url' => $actionUrl,
            'category' => isset($clean['category']) && filled($clean['category'])
                ? Str::limit(strip_tags((string) $clean['category']), 64, '')
                : null,
        ];
    }

    private function safeActionUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || $url === '[redacted]') {
            return null;
        }

        // Allow only relative app paths — never external schemes or protocol-relative URLs.
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return Str::limit($url, 500, '');
        }

        return null;
    }

    private function resolveRoleId(RoleSlug|Role|string $role): int
    {
        if ($role instanceof Role) {
            return (int) $role->id;
        }

        $slug = $role instanceof RoleSlug ? $role->value : $role;
        $id = Role::query()->where('slug', $slug)->value('id');

        if ($id === null) {
            throw new InvalidArgumentException("الدور غير معروف: {$slug}");
        }

        return (int) $id;
    }
}
