<?php

namespace App\Policies;

use App\Models\CustomerPurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Quotations\QuotationStatus;
use App\Support\SalesRequests\SalesRequestStatus;

class QuotationPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.view')) {
            return false;
        }

        return $this->scopes->allowsRecord(
            $user,
            'quotations.view',
            $quotation->authorizationContext(),
        );
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.manage');
    }

    public function createFromSalesRequest(User $user, SalesRequest $salesRequest): bool
    {
        if (! $user->can('quotations.manage') || $salesRequest->status !== SalesRequestStatus::ReadyForPricing) {
            return false;
        }

        return $user->can('view', $salesRequest);
    }

    /**
     * Margin pricing is Sales Manager only (PM1 / PM2).
     */
    public function update(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.approve_price') || ! $quotation->isDraft()) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    /**
     * Legacy Rep→SM submit path disabled — SM sets margin then finalizes from draft.
     */
    public function submitPrice(User $user, Quotation $quotation): bool
    {
        return false;
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        if (! $user->canApproveWorkflow('price_margin')) {
            return false;
        }

        // Preferred path: SM prices draft then finalizes (PM1).
        if ($quotation->status === QuotationStatus::Draft) {
            return $this->view($user, $quotation);
        }

        // Legacy pending path (if any remain): no self-approve of own submit.
        if ($quotation->status !== QuotationStatus::PendingPriceApproval) {
            return false;
        }

        if ((int) $quotation->submitted_by_user_id === (int) $user->id) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        // Reject only applies to legacy pending_price_approval submissions.
        if ($quotation->status !== QuotationStatus::PendingPriceApproval) {
            return false;
        }

        if ((int) $quotation->submitted_by_user_id === (int) $user->id) {
            return false;
        }

        if (! $user->canApproveWorkflow('price_margin')) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    public function send(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.send') || ! $quotation->status->canSend()) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.cancel') || $quotation->status->isTerminal()) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    public function supersede(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.cancel')) {
            return false;
        }

        if (! in_array($quotation->status, [QuotationStatus::Approved, QuotationStatus::Sent, QuotationStatus::Expired], true)) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    public function print(User $user, Quotation $quotation): bool
    {
        if (! $quotation->status->canOfferPdf()) {
            return false;
        }

        return $this->view($user, $quotation);
    }

    /**
     * Convert approved/sent quotation → customer PO (with PDF) + sales order draft.
     */
    public function convertToSalesOrder(User $user, Quotation $quotation): bool
    {
        if (! $quotation->status->canOfferPdf()) {
            return false;
        }

        if (! $user->can('create', CustomerPurchaseOrder::class)) {
            return false;
        }

        if (! $user->can('create', SalesOrder::class)) {
            return false;
        }

        return $this->view($user, $quotation);
    }
}
