<?php

namespace Database\Seeders;

use App\Models\ApprovalAuthority;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent sync of approved v1 roles, permissions, matrix, and approval defaults.
 * Does not create users or expose demo credentials.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = $this->syncRoles();
            $permissions = $this->syncPermissions();
            $this->syncMatrix($roles, $permissions);
            $this->syncApprovalAuthorities($roles);
        });

        foreach (Role::query()->pluck('id') as $roleId) {
            app(PermissionChecker::class)->forgetRoleCache((int) $roleId);
        }
    }

    /**
     * @return array<string, Role>
     */
    private function syncRoles(): array
    {
        $map = [];

        foreach (config('authorization.roles', []) as $slug => $meta) {
            $map[$slug] = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name_ar' => $meta['name_ar'],
                    'name_en' => $meta['name_en'],
                    'description_ar' => $meta['description_ar'] ?? null,
                    'is_system' => true,
                ]
            );
        }

        return $map;
    }

    /**
     * @return array<string, Permission>
     */
    private function syncPermissions(): array
    {
        $map = [];

        foreach (config('authorization.permissions', []) as $slug => $meta) {
            $map[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name_ar' => $meta['name_ar'],
                    'group_key' => $meta['group'],
                    'description_ar' => $meta['description_ar'] ?? null,
                ]
            );
        }

        return $map;
    }

    /**
     * @param  array<string, Role>  $roles
     * @param  array<string, Permission>  $permissions
     */
    private function syncMatrix(array $roles, array $permissions): void
    {
        foreach (config('authorization.matrix', []) as $roleSlug => $grants) {
            $role = $roles[$roleSlug] ?? null;
            if ($role === null) {
                continue;
            }

            $sync = [];
            foreach ($grants as $permissionSlug => $scopeOrTrue) {
                $permission = $permissions[$permissionSlug] ?? null;
                if ($permission === null) {
                    continue;
                }

                $sync[$permission->id] = [
                    'scope' => $scopeOrTrue === true ? null : (string) $scopeOrTrue,
                ];
            }

            $role->permissions()->sync($sync);
        }
    }

    /**
     * @param  array<string, Role>  $roles
     */
    private function syncApprovalAuthorities(array $roles): void
    {
        foreach (config('authorization.approval_authorities', []) as $workflowKey => $meta) {
            $role = $roles[$meta['default_role']] ?? null;
            if ($role === null) {
                continue;
            }

            ApprovalAuthority::query()->updateOrCreate(
                ['workflow_key' => $workflowKey],
                [
                    'label_ar' => $meta['label_ar'],
                    'permission_slug' => $meta['permission'],
                    'assigned_role_id' => $role->id,
                ]
            );
        }
    }
}
