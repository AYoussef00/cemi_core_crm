<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;

class CustomerPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $user->can('customers.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'customers.view',
            $customer->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $user->can('customers.manage')) {
            return false;
        }

        // Manage is unscoped in the matrix; record access still follows view scope
        // so a Sales Representative cannot edit another representative's customer.
        return $this->scopes->allowsRecord(
            $user,
            'customers.view',
            $customer->authorizationContext(),
        );
    }
}
