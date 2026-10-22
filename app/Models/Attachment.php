<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Attachment extends Model
{
    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attachment $attachment): void {
            if (blank($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * ASCII-safe fallback name for Content-Disposition (UTF-8 name sent separately).
     */
    public function downloadBasename(): string
    {
        $name = basename(str_replace(["\0", '/', '\\'], '', $this->original_name));

        return $name !== '' ? $name : ('attachment.'.$this->extension);
    }
}
