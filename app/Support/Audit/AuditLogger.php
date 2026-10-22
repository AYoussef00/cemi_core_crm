<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Central append-only audit writer for security- and configuration-sensitive actions.
 * Future domain modules should call this service rather than writing audit_logs directly.
 */
class AuditLogger
{
    public function __construct(private AttributeRedactor $redactor) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        $userAgent = Request::userAgent();
        if (is_string($userAgent) && strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $this->redactor->redact($properties) ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => $userAgent ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $keys
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function changes(array $before, array $after, array $keys): array
    {
        return $this->redactor->diff($before, $after, $keys);
    }

    public function redactor(): AttributeRedactor
    {
        return $this->redactor;
    }
}
