<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage');
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->can('users.manage');
    }

    public function activate(User $actor, User $target): bool
    {
        return $actor->can('users.manage');
    }

    public function sendPasswordReset(User $actor, User $target): bool
    {
        return $actor->can('users.manage');
    }
}
