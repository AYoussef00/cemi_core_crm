<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Sales\SalesOrderStatus;

class SalesOrderPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('sales_orders.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        if (! $user->can('sales_orders.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'sales_orders.view',
            $order->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('sales_orders.create_draft');
    }

    public function update(User $user, SalesOrder $order): bool
    {
        if (! $user->can('sales_orders.create_draft') || ! $order->isDraft()) {
            return false;
        }

        return $this->view($user, $order);
    }

    public function submit(User $user, SalesOrder $order): bool
    {
        if (! $user->can('sales_orders.submit') || ! $order->isDraft()) {
            return false;
        }

        return $this->view($user, $order);
    }

    public function approve(User $user, SalesOrder $order): bool
    {
        if (! $user->can('sales_orders.approve') || $order->status !== SalesOrderStatus::PendingApproval) {
            return false;
        }

        return $this->view($user, $order);
    }

    public function reject(User $user, SalesOrder $order): bool
    {
        return $this->approve($user, $order);
    }

    public function cancelApproved(User $user, SalesOrder $order): bool
    {
        if (! $user->can('sales_orders.cancel_approved') || $order->status !== SalesOrderStatus::Approved) {
            return false;
        }

        return $this->view($user, $order);
    }
}
