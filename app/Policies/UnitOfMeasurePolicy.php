<?php

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;

class UnitOfMeasurePolicy
{
    public function viewAny(User $user): bool
    {
        // Unit catalog is Administrator-managed; product viewers see unit names on products only.
        return $user->can('products.manage');
    }

    public function view(User $user, UnitOfMeasure $unit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function update(User $user, UnitOfMeasure $unit): bool
    {
        return $user->can('products.manage');
    }
}
