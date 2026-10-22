<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.manage') || $actor->can('users.manage');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage');
    }
}
