<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\PermissionChecker;
use App\Support\Authorization\RecordScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->orderBy('name_ar')->get();
        $selected = $request->integer('role')
            ? Role::query()->with('permissions')->findOrFail($request->integer('role'))
            : $roles->first();

        if ($selected !== null && ! $selected->relationLoaded('permissions')) {
            $selected->load('permissions');
        }

        $permissions = Permission::query()->orderBy('group_key')->orderBy('name_ar')->get()->groupBy('group_key');
        $current = [];
        if ($selected !== null) {
            foreach ($selected->permissions as $permission) {
                $current[$permission->slug] = [
                    'enabled' => true,
                    'scope' => $permission->pivot->scope,
                ];
            }
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'selected' => $selected,
            'permissionGroups' => $permissions,
            'current' => $current,
            'scopes' => RecordScope::cases(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role, PermissionChecker $checker, AuditLogger $audit): RedirectResponse
    {
        $role->load('permissions');
        $before = $this->permissionSnapshot($role);

        $allPermissions = Permission::query()->get()->keyBy('slug');
        $sync = [];

        foreach ($request->input('grants', []) as $slug => $grant) {
            if (! ($grant['enabled'] ?? false)) {
                continue;
            }

            $permission = $allPermissions->get($slug);
            if ($permission === null) {
                continue;
            }

            $scope = $grant['scope'] ?? null;
            $sync[$permission->id] = [
                'scope' => filled($scope) ? (string) $scope : null,
            ];
        }

        DB::transaction(function () use ($role, $sync, $checker, $audit, $before): void {
            $role->permissions()->sync($sync);
            $checker->forgetRoleCache((int) $role->id);
            $role->load('permissions');

            $audit->record('role.permissions_updated', $role, [
                'role_slug' => $role->slug,
                'before' => $before,
                'after' => $this->permissionSnapshot($role),
            ]);
        });

        return redirect()
            ->route('admin.roles.index', ['role' => $role->id])
            ->with('status_ar', 'تم تحديث صلاحيات الدور.');
    }

    /**
     * @return array<string, string|null>
     */
    private function permissionSnapshot(Role $role): array
    {
        $snapshot = [];

        foreach ($role->permissions as $permission) {
            $snapshot[$permission->slug] = $permission->pivot->scope;
        }

        ksort($snapshot);

        return $snapshot;
    }
}
