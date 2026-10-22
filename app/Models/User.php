<?php

namespace App\Models;

use App\Support\Authorization\PermissionChecker;
use App\Support\Authorization\RecordScope;
use App\Support\Authorization\RoleSlug;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'is_active', 'deactivated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        return app(PermissionChecker::class)->allows($this, $permission);
    }

    public function permissionScope(string $permission): ?RecordScope
    {
        return app(PermissionChecker::class)->scopeFor($this, $permission);
    }

    public function canApproveWorkflow(string $workflowKey): bool
    {
        return app(PermissionChecker::class)->canApprove($this, $workflowKey);
    }

    public function hasRole(RoleSlug|string $slug): bool
    {
        $value = $slug instanceof RoleSlug ? $slug->value : $slug;

        return $this->role?->slug === $value;
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole(RoleSlug::Administrator);
    }

    public function activate(): void
    {
        $this->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();
    }

    public function deactivate(): void
    {
        $this->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->save();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
