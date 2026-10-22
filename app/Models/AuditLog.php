<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only audit record. Ordinary application paths must not update or delete rows.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('سجلات التدقيق للقراءة فقط ولا يمكن تعديلها.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('سجلات التدقيق للقراءة فقط ولا يمكن حذفها.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Arabic label for known action keys; unknown actions fall back to the raw key.
     * Uses array access (not dotted config paths) because action keys contain dots.
     */
    public function actionLabelAr(): string
    {
        $actions = config('audit.actions', []);

        return (string) ($actions[$this->action] ?? $this->action);
    }
}
