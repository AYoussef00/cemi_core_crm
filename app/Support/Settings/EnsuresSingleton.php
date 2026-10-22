<?php

namespace App\Support\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Application-level singleton helpers for v1 one-company / one-warehouse tables.
 * Complements the unique `singleton_key` database constraint.
 *
 * @mixin Model
 */
trait EnsuresSingleton
{
    public static function singletonKey(): string
    {
        return 'default';
    }

    public static function current(): ?static
    {
        return static::query()
            ->where('singleton_key', static::singletonKey())
            ->first();
    }

    public static function isConfigured(): bool
    {
        return static::current() !== null;
    }

    /**
     * Create the single row if missing, otherwise update it.
     * Concurrent create attempts are serialized and rejected if a second row would result.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function saveSingleton(array $attributes): static
    {
        return DB::transaction(function () use ($attributes) {
            /** @var static|null $existing */
            $existing = static::query()
                ->where('singleton_key', static::singletonKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->fill($attributes)->save();

                return $existing->fresh();
            }

            $count = static::query()->lockForUpdate()->count();
            if ($count > 0) {
                throw ValidationException::withMessages([
                    'singleton' => static::singletonAlreadyExistsMessage(),
                ]);
            }

            return static::query()->create(array_merge($attributes, [
                'singleton_key' => static::singletonKey(),
            ]));
        });
    }

    protected static function singletonAlreadyExistsMessage(): string
    {
        return 'يُسمح بسجل واحد فقط في الإصدار الحالي.';
    }

    protected static function bootEnsuresSingleton(): void
    {
        static::creating(function (Model $model): void {
            $key = $model->getAttribute('singleton_key') ?: static::singletonKey();
            $model->setAttribute('singleton_key', $key);

            // v1: at most one row of this type, regardless of key value.
            if (static::query()->exists()) {
                throw new RuntimeException(static::singletonAlreadyExistsMessage());
            }
        });
    }
}
