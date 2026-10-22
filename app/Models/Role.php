<?php

namespace App\Models;

use App\Support\Authorization\RoleSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'slug',
        'name_ar',
        'name_en',
        'description_ar',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot('scope')
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function slugEnum(): ?RoleSlug
    {
        return RoleSlug::tryFrom($this->slug);
    }

    public function isAdministrator(): bool
    {
        return $this->slug === RoleSlug::Administrator->value;
    }
}
