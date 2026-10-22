<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\SalesRequests\SalesRequestStatus;

class SalesRequestPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('sales_requests.view');
    }

    public function view(User $user, SalesRequest $salesRequest): bool
    {
        if (! $user->can('sales_requests.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'sales_requests.view',
            $salesRequest->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('sales_requests.manage');
    }

    public function createForCustomer(User $user, Customer $customer): bool
    {
        if (! $user->can('sales_requests.manage')) {
            return false;
        }

        return $user->can('view', $customer);
    }

    public function update(User $user, SalesRequest $salesRequest): bool
    {
        if (! $user->can('sales_requests.manage') || ! $salesRequest->isDraft()) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'sales_requests.view',
            $salesRequest->authorizationContext(),
        );
    }

    public function submit(User $user, SalesRequest $salesRequest): bool
    {
        return $this->update($user, $salesRequest);
    }

    public function cancel(User $user, SalesRequest $salesRequest): bool
    {
        if (! $user->can('sales_requests.manage') || $salesRequest->status === SalesRequestStatus::Cancelled) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'sales_requests.view',
            $salesRequest->authorizationContext(),
        );
    }
}
