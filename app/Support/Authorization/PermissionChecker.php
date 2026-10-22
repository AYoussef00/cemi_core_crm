<?php

namespace App\Support\Authorization;

use App\Models\ApprovalAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side permission and approval checks. UI hiding is not authorization.
 */
class PermissionChecker
{
    public function allows(User $user, string $permission): bool
    {
        if (! $user->is_active || $user->role_id === null) {
            return false;
        }

        $grants = $this->grantsFor($user);

        return array_key_exists($permission, $grants);
    }

    public function scopeFor(User $user, string $permission): ?RecordScope
    {
        if (! $this->allows($user, $permission)) {
            return null;
        }

        $value = $this->grantsFor($user)[$permission] ?? null;

        if ($value === null || $value === true || $value === '') {
            return null;
        }

        return RecordScope::tryFrom((string) $value);
    }

    /**
     * Approval workflows require both: the configured role match AND the permission.
     * No silent Administrator bypass for sales approvals.
     */
    public function canApprove(User $user, string $workflowKey): bool
    {
        if (! $user->is_active || $user->role_id === null) {
            return false;
        }

        $authority = ApprovalAuthority::query()
            ->where('workflow_key', $workflowKey)
            ->first();

        if ($authority === null) {
            $defaults = config('authorization.approval_authorities.'.$workflowKey);
            if (! is_array($defaults)) {
                return false;
            }

            $permission = (string) $defaults['permission'];
            $defaultRole = (string) $defaults['default_role'];

            return $user->role?->slug === $defaultRole
                && $this->allows($user, $permission);
        }

        if ((int) $authority->assigned_role_id !== (int) $user->role_id) {
            return false;
        }

        return $this->allows($user, $authority->permission_slug);
    }

    /**
     * @return array<string, true|string>
     */
    public function grantsFor(User $user): array
    {
        if ($user->role_id === null) {
            return [];
        }

        $role = $user->relationLoaded('role') ? $user->role : $user->role()->with('permissions')->first();

        if ($role === null) {
            return [];
        }

        if ($role->relationLoaded('permissions')) {
            return $this->mapGrants($role->permissions);
        }

        return Cache::remember(
            $this->cacheKey((int) $role->id),
            now()->addMinutes(10),
            function () use ($role) {
                $role->load('permissions');

                return $this->mapGrants($role->permissions);
            }
        );
    }

    public function forgetRoleCache(int $roleId): void
    {
        Cache::forget($this->cacheKey($roleId));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Permission>  $permissions
     * @return array<string, true|string>
     */
    private function mapGrants($permissions): array
    {
        $grants = [];

        foreach ($permissions as $permission) {
            $scope = $permission->pivot->scope ?? null;
            $grants[$permission->slug] = filled($scope) ? (string) $scope : true;
        }

        return $grants;
    }

    private function cacheKey(int $roleId): string
    {
        return 'authz.role.'.$roleId.'.grants';
    }
}
