<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Warehouse\DeliveryStatus;

class InvoicePolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'invoices.view',
            $invoice->authorizationContext(),
        );
    }

    public function issue(User $user): bool
    {
        return $user->can('invoices.issue');
    }

    public function issueFromDelivery(User $user, Delivery $delivery): bool
    {
        // PF3 uniqueness is enforced in InvoiceWorkflow (DomainException → form error), not 403.
        return $user->can('invoices.issue') && $delivery->status === DeliveryStatus::Confirmed;
    }
}
