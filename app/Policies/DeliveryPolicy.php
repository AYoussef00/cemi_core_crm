<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Warehouse\DeliveryStatus;

class DeliveryPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('delivery.view');
    }

    public function view(User $user, Delivery $delivery): bool
    {
        if (! $user->can('delivery.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'delivery.view',
            $delivery->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('delivery.manage');
    }

    public function createForSalesOrder(User $user, SalesOrder $order): bool
    {
        if (! $user->can('delivery.manage') || $order->status !== SalesOrderStatus::Approved) {
            return false;
        }

        if ($order->isFullyDelivered()) {
            return false;
        }

        // Must be able to view the SO (Warehouse/SM All).
        return $user->can('view', $order);
    }

    public function update(User $user, Delivery $delivery): bool
    {
        if (! $user->can('delivery.manage') || $delivery->status !== DeliveryStatus::Draft) {
            return false;
        }

        return $this->view($user, $delivery);
    }

    public function markPendingPod(User $user, Delivery $delivery): bool
    {
        if (! $user->can('delivery.manage') || $delivery->status !== DeliveryStatus::Draft) {
            return false;
        }

        return $this->view($user, $delivery);
    }

    public function confirm(User $user, Delivery $delivery): bool
    {
        if (! $user->can('delivery.manage') || ! $delivery->status->isOpen()) {
            return false;
        }

        return $this->view($user, $delivery);
    }

    public function cancel(User $user, Delivery $delivery): bool
    {
        if (! $user->can('delivery.cancel') || ! $delivery->status->isOpen()) {
            return false;
        }

        return $this->view($user, $delivery);
    }

    public function managePreparation(User $user, SalesOrder $order): bool
    {
        if (! $user->can('delivery.manage') || $order->status !== SalesOrderStatus::Approved) {
            return false;
        }

        return $user->can('view', $order);
    }

    public function uploadPod(User $user, Delivery $delivery): bool
    {
        if (! $user->can('delivery.manage') || ! $delivery->status->isOpen()) {
            return false;
        }

        return $this->view($user, $delivery);
    }
}
