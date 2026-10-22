<?php

namespace App\Support\Audit;

/**
 * Explicit denylist redaction for audit payloads. Never store credentials or secrets.
 */
class AttributeRedactor
{
    /**
     * Attribute keys (case-insensitive) that must never appear in audit properties.
     *
     * @var list<string>
     */
    private const DENIED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'plain_text_token',
        'api_token',
        'api_key',
        'secret',
        'client_secret',
        'mail_password',
        'smtp_password',
        'authorization',
        'session_id',
        'session',
        'cookie',
        'cookies',
        'content',
        'file_contents',
        'payload',
    ];

    /**
     * Safe boolean/metadata keys that contain a denied substring but must keep their value.
     *
     * @var list<string>
     */
    private const ALLOWED_KEYS = [
        'password_changed',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function redact(array $attributes): array
    {
        $clean = [];

        foreach ($attributes as $key => $value) {
            $normalized = strtolower((string) $key);

            if ($this->isDenied($normalized)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->redact($value);

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Build a before/after change set for allow-listed keys only.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $keys
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function diff(array $before, array $after, array $keys): array
    {
        $beforeOut = [];
        $afterOut = [];

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old == $new) {
                continue;
            }

            $beforeOut[$key] = $old;
            $afterOut[$key] = $new;
        }

        return [
            'before' => $this->redact($beforeOut),
            'after' => $this->redact($afterOut),
        ];
    }

    private function isDenied(string $normalizedKey): bool
    {
        if (in_array($normalizedKey, self::ALLOWED_KEYS, true)) {
            return false;
        }

        if (in_array($normalizedKey, self::DENIED_KEYS, true)) {
            return true;
        }

        foreach (['password', 'secret', 'token', 'credential'] as $needle) {
            if (str_contains($normalizedKey, $needle)) {
                return true;
            }
        }

        return false;
    }
}
