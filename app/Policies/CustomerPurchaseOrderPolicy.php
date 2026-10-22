<?php

namespace App\Policies;

use App\Models\CustomerPurchaseOrder;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Sales\CustomerPurchaseOrderStatus;

class CustomerPurchaseOrderPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('customer_pos.view');
    }

    public function view(User $user, CustomerPurchaseOrder $po): bool
    {
        if (! $user->can('customer_pos.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'customer_pos.view',
            $po->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('customer_pos.manage');
    }

    public function update(User $user, CustomerPurchaseOrder $po): bool
    {
        if (! $user->can('customer_pos.manage') || $po->status !== CustomerPurchaseOrderStatus::Open) {
            return false;
        }

        return $this->view($user, $po);
    }

    public function cancel(User $user, CustomerPurchaseOrder $po): bool
    {
        if (! $user->can('customer_pos.cancel') || $po->status !== CustomerPurchaseOrderStatus::Open) {
            return false;
        }

        return $this->view($user, $po);
    }
}
