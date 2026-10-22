<?php

namespace App\Policies;

use App\Models\ProcurementTask;
use App\Models\User;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Procurement\ProcurementTaskStatus;

class ProcurementTaskPolicy
{
    public function __construct(private RecordScopeResolver $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, ProcurementTask $task): bool
    {
        if (! $user->can('procurement.view')) {
            return false;
        }

        $scope = $this->scopes->for($user, 'procurement.view');
        if ($scope === null) {
            return true;
        }

        return $this->scopes->allowsRecord(
            $user,
            'procurement.view',
            $task->authorizationContext(),
        );
    }

    public function recordCost(User $user, ProcurementTask $task): bool
    {
        if (! $user->can('procurement.manage') || $task->status !== ProcurementTaskStatus::Open) {
            return false;
        }

        return $this->view($user, $task);
    }

    public function cancel(User $user, ProcurementTask $task): bool
    {
        if (! $user->can('procurement.cancel') || $task->status !== ProcurementTaskStatus::Open) {
            return false;
        }

        return $this->view($user, $task);
    }
}
