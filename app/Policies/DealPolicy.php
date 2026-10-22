<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Support\Sales\DealStatus;

class DealPolicy
{
    public function view(User $user, Deal $deal): bool
    {
        return $user->can('invoices.view')
            || $user->can('sales_orders.view')
            || $user->can('deals.close');
    }

    public function close(User $user, Deal $deal): bool
    {
        return $user->can('deals.close') && $deal->status === DealStatus::Open;
    }
}
