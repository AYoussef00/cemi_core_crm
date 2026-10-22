<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Authorization\RoleSlug;
use App\Support\Visits\VisitStatus;

class CustomerVisitPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('visits.view');
    }

    public function view(User $user, CustomerVisit $visit): bool
    {
        if (! $user->can('visits.view')) {
            return false;
        }

        $visit->loadMissing('customer');

        return $this->scopes->allowsRecord(
            $user,
            'visits.view',
            $visit->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('visits.manage');
    }

    public function createForCustomer(User $user, Customer $customer): bool
    {
        if (! $user->can('visits.manage')) {
            return false;
        }

        // Cust/Assigned: only for customers in the user's customer view scope.
        return $user->can('view', $customer);
    }

    public function update(User $user, CustomerVisit $visit): bool
    {
        if (! $user->can('visits.manage') || ! $visit->isScheduled()) {
            return false;
        }

        $visit->loadMissing('customer');

        return $this->scopes->allowsRecord(
            $user,
            'visits.view',
            $visit->authorizationContext(),
        );
    }

    public function complete(User $user, CustomerVisit $visit): bool
    {
        return $this->update($user, $visit);
    }

    /**
     * Cancel: Sales Rep = own assignee only; Sales Manager = any scheduled visit in All scope.
     * Does not broaden visit view scope (still Cust for Reps).
     */
    public function cancel(User $user, CustomerVisit $visit): bool
    {
        if (! $user->can('visits.manage') || $visit->status !== VisitStatus::Scheduled) {
            return false;
        }

        $visit->loadMissing('customer');

        if ($user->hasRole(RoleSlug::SalesManager)) {
            return $this->scopes->allowsRecord(
                $user,
                'visits.view',
                $visit->authorizationContext(),
            );
        }

        if ($user->hasRole(RoleSlug::SalesRepresentative)) {
            return (int) $visit->assigned_user_id === (int) $user->id;
        }

        return false;
    }
}
