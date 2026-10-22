<?php

namespace App\Support\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Registers a Gate ability for every permission slug.
 * No Gate::before super-admin bypass — Administrator only gets matrix grants.
 */
class GateRegistrar
{
    public function register(): void
    {
        foreach (array_keys(config('authorization.permissions', [])) as $slug) {
            Gate::define($slug, function (?User $user) use ($slug): bool {
                if ($user === null) {
                    return false;
                }

                return app(PermissionChecker::class)->allows($user, $slug);
            });
        }

        foreach (array_keys(config('authorization.approval_authorities', [])) as $workflowKey) {
            Gate::define('approve.'.$workflowKey, function (?User $user) use ($workflowKey): bool {
                if ($user === null) {
                    return false;
                }

                return app(PermissionChecker::class)->canApprove($user, $workflowKey);
            });
        }
    }
}
