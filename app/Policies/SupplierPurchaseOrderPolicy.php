<?php

namespace App\Policies;

use App\Models\SupplierPurchaseOrder;
use App\Models\User;

class SupplierPurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view') || $user->can('stock.view');
    }

    public function view(User $user, SupplierPurchaseOrder $supplier_po): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return SupplierPurchaseOrder::query()
            ->visibleTo($user)
            ->whereKey($supplier_po->id)
            ->exists();
    }

    public function markOrdered(User $user, SupplierPurchaseOrder $supplier_po): bool
    {
        if (! $user->can('procurement.manage') || ! $supplier_po->canMarkOrdered()) {
            return false;
        }

        return $this->view($user, $supplier_po);
    }

    public function receive(User $user, SupplierPurchaseOrder $supplier_po): bool
    {
        if (! $user->can('delivery.manage') || ! $supplier_po->canReceive()) {
            return false;
        }

        return $this->view($user, $supplier_po);
    }

    public function print(User $user, SupplierPurchaseOrder $supplier_po): bool
    {
        return $this->view($user, $supplier_po);
    }
}
