<?php

namespace App\Support\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Prevents removal/deactivation/demotion of the last active Administrator.
 */
class AdministratorGuard
{
    public function activeAdministratorCount(?int $excludingUserId = null): int
    {
        $adminRoleId = Role::query()
            ->where('slug', RoleSlug::Administrator->value)
            ->value('id');

        if ($adminRoleId === null) {
            return 0;
        }

        return User::query()
            ->where('role_id', $adminRoleId)
            ->where('is_active', true)
            ->when($excludingUserId, fn ($q) => $q->where('id', '!=', $excludingUserId))
            ->count();
    }

    public function assertMayDeactivate(User $target): void
    {
        if (! $target->isAdministrator() || ! $target->is_active) {
            return;
        }

        if ($this->activeAdministratorCount($target->id) < 1) {
            throw ValidationException::withMessages([
                'is_active' => 'لا يمكن إيقاف آخر مدير نظام نشط.',
            ]);
        }
    }

    public function assertMayChangeRole(User $target, ?int $newRoleId): void
    {
        if (! $target->isAdministrator() || ! $target->is_active) {
            return;
        }

        $adminRoleId = Role::query()
            ->where('slug', RoleSlug::Administrator->value)
            ->value('id');

        if ($adminRoleId === null) {
            return;
        }

        if ((int) $newRoleId === (int) $adminRoleId) {
            return;
        }

        if ($this->activeAdministratorCount($target->id) < 1) {
            throw ValidationException::withMessages([
                'role_id' => 'لا يمكن تغيير دور آخر مدير نظام نشط.',
            ]);
        }
    }
}
