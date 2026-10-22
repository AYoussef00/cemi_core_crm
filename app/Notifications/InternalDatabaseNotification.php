<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database-only internal notification. No mail/SMS/broadcast channels.
 */
class InternalDatabaseNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{title_ar: string, body_ar?: string|null, action_url?: string|null, category?: string|null}  $payload
     */
    public function __construct(private array $payload) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title_ar' => (string) ($this->payload['title_ar'] ?? ''),
            'body_ar' => $this->payload['body_ar'] ?? null,
            'action_url' => $this->payload['action_url'] ?? null,
            'category' => $this->payload['category'] ?? null,
        ];
    }
}
