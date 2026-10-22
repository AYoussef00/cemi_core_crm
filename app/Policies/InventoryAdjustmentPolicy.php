<?php

namespace App\Policies;

use App\Models\InventoryAdjustment;
use App\Models\User;
use App\Support\Inventory\InventoryAdjustmentStatus;

class InventoryAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory_adjustment.record')
            || $user->can('inventory_adjustment.approve')
            || $user->canApproveWorkflow('inventory_adjustment');
    }

    public function view(User $user, InventoryAdjustment $adjustment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory_adjustment.record');
    }

    public function approve(User $user, InventoryAdjustment $adjustment): bool
    {
        if ($adjustment->status !== InventoryAdjustmentStatus::Pending) {
            return false;
        }

        if ((int) $adjustment->recorded_by_user_id === (int) $user->id) {
            return false;
        }

        return $user->canApproveWorkflow('inventory_adjustment');
    }

    public function reject(User $user, InventoryAdjustment $adjustment): bool
    {
        return $this->approve($user, $adjustment);
    }
}
