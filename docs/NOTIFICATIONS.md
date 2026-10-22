# Internal notifications foundation

**Phase:** 1 — Prompt 7  
**Channel:** In-app database only (no email / WhatsApp / push / websocket in this step)

## Service

`App\Support\Notifications\InternalNotifier`

| Method | Behavior |
|---|---|
| `notifyUser(User, payload)` | Notifies one **active** user |
| `notifyRole(RoleSlug\|Role\|string, payload)` | Notifies all **active** users with that role |
| `notifyMany(iterable, payload)` | Notifies each active user in the list |
| `sanitize(payload)` | Requires `title_ar`; strips HTML; redacts secrets; allows only relative `action_url` |

## Payload (stored in `notifications.data`)

- `title_ar` (required)
- `body_ar` (optional)
- `action_url` (optional, must start with `/`, not `//`)
- `category` (optional)

Passwords, tokens, sessions, and file contents are never stored.

## UI

- `/account/notifications` — list, mark one / mark all as read
- Unread badge in sidebar + topbar bell
- Floating **alert room** (bottom-left FAB): read-only notification stream (no chat input); newest first; soft chime on new items; mute + mark read; polls `GET /account/notifications/feed`
- Users can only access their own notification UUIDs (404 otherwise)

## Alert-room feed

`GET /account/notifications/feed` (auth, JSON, throttle 60/min) returns `{ unread_count, latest_id, items[] }` where each item has `id`, `title_ar`, `body_ar`, `action_url`, `category`, `unread`, `created_at`, `created_label`.
