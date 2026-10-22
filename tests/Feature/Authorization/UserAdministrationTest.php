<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_administrator_can_create_user_with_exactly_one_role(): void
    {
        $admin = User::factory()->administrator()->create();
        $roleId = Role::query()->where('slug', RoleSlug::SalesRepresentative->value)->value('id');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'مندوب جديد',
            'email' => 'rep.new@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role_id' => $roleId,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'rep.new@example.com',
            'role_id' => $roleId,
            'is_active' => 1,
        ]);
    }

    public function test_user_create_requires_a_role(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => 'بلا دور',
            'email' => 'norole@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertRedirect(route('admin.users.create'));
        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseMissing('users', ['email' => 'norole@example.com']);
    }

    public function test_non_administrator_cannot_access_user_administration(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.users.store'), [])->assertForbidden();
        $this->actingAs($manager)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.approvals.edit'))->assertForbidden();
    }

    public function test_sales_representative_cannot_escalate_to_administrator_via_user_update(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $target = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $adminRoleId = Role::query()->where('slug', RoleSlug::Administrator->value)->value('id');

        $this->actingAs($rep)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $adminRoleId,
            'is_active' => '1',
        ])->assertForbidden();

        $target->refresh();
        $this->assertNotSame($adminRoleId, $target->role_id);
    }

    public function test_administrator_can_update_role_and_trigger_password_reset(): void
    {
        Notification::fake();

        $admin = User::factory()->administrator()->create();
        $user = User::factory()->withRole(RoleSlug::Warehouse)->create([
            'email' => 'warehouse.user@example.com',
        ]);
        $accountsRoleId = Role::query()->where('slug', RoleSlug::Accounts->value)->value('id');

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'حسابات',
            'email' => 'warehouse.user@example.com',
            'role_id' => $accountsRoleId,
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.edit', $user));

        $this->assertSame($accountsRoleId, $user->fresh()->role_id);

        $this->actingAs($admin)
            ->post(route('admin.users.password-reset', $user))
            ->assertRedirect();

        Notification::assertSentTo($user->fresh(), ResetPasswordNotification::class);
    }

    public function test_cannot_deactivate_last_active_administrator(): void
    {
        $onlyAdmin = User::factory()->administrator()->create();

        $this->actingAs($onlyAdmin)
            ->from(route('admin.users.edit', $onlyAdmin))
            ->post(route('admin.users.deactivate', $onlyAdmin))
            ->assertRedirect(route('admin.users.edit', $onlyAdmin))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($onlyAdmin->fresh()->is_active);
    }

    public function test_cannot_demote_last_active_administrator(): void
    {
        $onlyAdmin = User::factory()->administrator()->create();
        $repRoleId = Role::query()->where('slug', RoleSlug::SalesRepresentative->value)->value('id');

        $this->actingAs($onlyAdmin)
            ->from(route('admin.users.edit', $onlyAdmin))
            ->put(route('admin.users.update', $onlyAdmin), [
                'name' => $onlyAdmin->name,
                'email' => $onlyAdmin->email,
                'role_id' => $repRoleId,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.edit', $onlyAdmin))
            ->assertSessionHasErrors('role_id');

        $this->assertTrue($onlyAdmin->fresh()->isAdministrator());
    }

    public function test_second_administrator_can_be_deactivated(): void
    {
        $adminA = User::factory()->administrator()->create();
        $adminB = User::factory()->administrator()->create();

        $this->actingAs($adminA)
            ->post(route('admin.users.deactivate', $adminB))
            ->assertRedirect();

        $this->assertFalse($adminB->fresh()->is_active);
    }

    public function test_administrator_can_configure_role_permissions_without_gaining_sales_approvals_by_default(): void
    {
        $admin = User::factory()->administrator()->create();
        $adminRole = Role::query()->where('slug', RoleSlug::Administrator->value)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.roles.index', ['role' => $adminRole->id]))
            ->assertOk()
            ->assertSee('الأدوار والصلاحيات', false);

        $this->assertFalse($admin->fresh()->can('sales_orders.approve'));
    }

    public function test_administrator_can_update_approval_authority_assignment(): void
    {
        $admin = User::factory()->administrator()->create();
        $accountsRoleId = Role::query()->where('slug', RoleSlug::Accounts->value)->value('id');
        $authority = \App\Models\ApprovalAuthority::query()->where('workflow_key', 'sales_order')->firstOrFail();

        $payload = [];
        foreach (\App\Models\ApprovalAuthority::query()->get() as $row) {
            $payload[$row->id] = [
                'assigned_role_id' => $row->workflow_key === 'sales_order'
                    ? $accountsRoleId
                    : $row->assigned_role_id,
            ];
        }

        $this->actingAs($admin)
            ->put(route('admin.approvals.update'), ['authorities' => $payload])
            ->assertRedirect(route('admin.approvals.edit'));

        $this->assertSame($accountsRoleId, $authority->fresh()->assigned_role_id);

        // Accounts still needs sales_orders.approve permission — defaults do not grant it,
        // so canApprove remains false (no silent privilege invention).
        $accountsUser = User::factory()->withRole(RoleSlug::Accounts)->create();
        $this->assertFalse($accountsUser->canApproveWorkflow('sales_order'));
    }

    public function test_arabic_rtl_user_admin_screens_render(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('المستخدمون', false);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('مستخدم جديد', false)
            ->assertSee('الدور (واحد فقط)', false);
    }
}
