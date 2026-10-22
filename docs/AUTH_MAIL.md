# Authentication & mail configuration

Password recovery uses Laravel’s password broker and notification mail. **Never hard-code mail secrets.** Credentials belong only in environment / deployment secrets — never in the database, source, HTML, logs, or audit payloads.

## Admin status UI (Phase 1 Prompt 8)

- Route: `GET /admin/settings/mail` (`settings.manage` — Administrator)
- Shows effective Laravel mailer/transport, masked from-address, and (for SMTP) host/port with **masked** username and password placeholders
- Explains that changes are made in deployment env and may need `php artisan config:clear` / process restart
- **Test send:** `POST /admin/settings/mail/test` (CSRF + throttle `3,1`) sends only to the **authenticated administrator’s own email** — no arbitrary recipient field
- Local mailers `log` / `array` are labelled as **local-only** (not inbox delivery)
- Audit: `mail.test_sent` / `mail.test_failed` with masked recipient and mailer name — no passwords

## Local / testing

- Default `.env.example` uses `MAIL_MAILER=log` (messages written to the log).
- PHPUnit uses `MAIL_MAILER=array` (in-memory; no network). Feature tests use `Mail::fake()` for the admin test-send path — **no real external email**.
- The forgot-password screen shows an actionable warning when the active mailer is `log` or `array`.

## Production

Set in `.env` (examples):

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.example"
MAIL_FROM_NAME="${APP_NAME}"
```

Also for HTTPS deployments:

```env
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

After changing mail settings, clear config cache if used: `php artisan config:clear`.

Third-party providers (SES, Postmark, Resend, …) are configured the same way via env/SDK — the admin page reports readiness from config; live inbox delivery is verified only when a real provider is configured in a non-test environment (out of scope for automated verification).
