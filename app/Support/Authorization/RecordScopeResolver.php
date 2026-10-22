<?php

namespace App\Support\Authorization;

use App\Models\User;

/**
 * Resolves documented record scopes for resources.
 * Domain query enforcement attaches when those models exist;
 * this foundation exposes the approved scope vocabulary now.
 */
class RecordScopeResolver
{
    public function __construct(private PermissionChecker $checker) {}

    public function for(User $user, string $permission): ?RecordScope
    {
        return $this->checker->scopeFor($user, $permission);
    }

    /**
     * Whether the user may access a record given owner/assignment metadata.
     * Used by future domain policies; tested here against the approved matrix.
     *
     * @param  array{
     *     owner_user_id?: int|null,
     *     assigned_sales_rep_id?: int|null,
     *     is_work_related?: bool,
     *     is_referred_to_procurement?: bool
     * }  $context
     */
    public function allowsRecord(User $user, string $permission, array $context = []): bool
    {
        $scope = $this->for($user, $permission);

        if ($scope === null) {
            // Permission without scope = full allow for that action type when granted
            return $this->checker->allows($user, $permission);
        }

        return match ($scope) {
            RecordScope::All, RecordScope::Admin => true,
            RecordScope::Own => isset($context['owner_user_id'])
                && (int) $context['owner_user_id'] === (int) $user->id,
            RecordScope::Assigned, RecordScope::Cust => isset($context['assigned_sales_rep_id'])
                && (int) $context['assigned_sales_rep_id'] === (int) $user->id,
            RecordScope::Work => (bool) ($context['is_work_related'] ?? false),
            RecordScope::Referred => (bool) ($context['is_referred_to_procurement'] ?? false),
            RecordScope::Cost => true,
        };
    }
}
