<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use App\Support\Finance\CollectionStatus;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('collections.record') || $user->can('invoices.view');
    }

    public function view(User $user, Collection $collection): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('collections.record');
    }

    public function confirm(User $user, Collection $collection): bool
    {
        return $user->can('collections.record') && $collection->status === CollectionStatus::Draft;
    }

    public function requestReversal(User $user, Collection $collection): bool
    {
        return $user->can('collection_reversal.request')
            && $collection->status === CollectionStatus::Confirmed;
    }

    public function decideReversal(User $user): bool
    {
        return $user->can('collection_reversal.approve');
    }
}
