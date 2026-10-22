<?php

namespace App\Policies;

use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\Credit\CreditLimitRequestStatus;

class CreditLimitRequestPolicy
{
    /**
     * Credit profile / history visibility.
     * credit_limit.review and credit_limit.approve are unscoped in the matrix (company-wide).
     * Sales Rep profiles remain limited to customers they can view (Assigned).
     */
    public function viewProfile(User $user, Customer $customer): bool
    {
        if ($user->can('credit_limit.review') || $user->can('credit_limit.approve')) {
            return true;
        }

        if (! $user->can('view', $customer)) {
            return false;
        }

        return $user->can('credit_limit.request')
            || $user->can('invoices.view')
            || $user->can('reports.ar');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('credit_limit.request')
            || $user->can('credit_limit.review')
            || $user->can('credit_limit.approve')
            || $user->can('invoices.view')
            || $user->can('reports.ar');
    }

    public function view(User $user, CreditLimitRequest $request): bool
    {
        if ($user->can('credit_limit.review') || $user->can('credit_limit.approve')) {
            return true;
        }

        $customer = $request->customer;

        return $customer !== null && $this->viewProfile($user, $customer);
    }

    public function create(User $user, Customer $customer): bool
    {
        if (! $user->can('credit_limit.request')) {
            return false;
        }

        return $user->can('view', $customer);
    }

    public function review(User $user, CreditLimitRequest $request): bool
    {
        if ($request->status !== CreditLimitRequestStatus::PendingReview) {
            return false;
        }

        return $user->canApproveWorkflow('credit_limit_review');
    }

    public function decideFinal(User $user, CreditLimitRequest $request): bool
    {
        if ($request->status !== CreditLimitRequestStatus::PendingFinal) {
            return false;
        }

        return $user->canApproveWorkflow('credit_limit_final');
    }
}
