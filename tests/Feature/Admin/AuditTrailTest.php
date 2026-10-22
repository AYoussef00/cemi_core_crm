<?php

namespace Tests\Feature\Admin;

use App\Models\ApprovalAuthority;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AttributeRedactor;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_company_and_warehouse_mutations_create_audit_records(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->put(route('admin.settings.company.update'), [
            'name_ar' => 'شركة سيمي',
            'address_ar' => 'الرياض',
            'contact_phone' => '+966500000000',
            'contact_email' => 'ops@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company.created',
            'actor_user_id' => $admin->id,
        ]);

        $created = AuditLog::query()->where('action', 'company.created')->first();
        $this->assertNotNull($created?->created_at);
        $this->assertSame('شركة سيمي', data_get($created->properties, 'after.name_ar'));

        $this->actingAs($admin)->put(route('admin.settings.company.update'), [
            'name_ar' => 'شركة سيمي المحدودة',
            'address_ar' => 'جدة',
            'contact_phone' => null,
            'contact_email' => null,
        ])->assertRedirect();

        $updated = AuditLog::query()->where('action', 'company.updated')->latest('id')->first();
        $this->assertSame($admin->id, $updated?->actor_user_id);
        $this->assertSame('شركة سيمي', data_get($updated->properties, 'before.name_ar'));
        $this->assertSame('شركة سيمي المحدودة', data_get($updated->properties, 'after.name_ar'));

        $this->actingAs($admin)->put(route('admin.settings.warehouse.update'), [
            'name_ar' => 'المستودع الرئيسي',
            'address_ar' => null,
            'contact_phone' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'warehouse.created',
            'actor_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.settings.warehouse.update'), [
            'name_ar' => 'مستودع الرياض',
            'address_ar' => 'المنطقة الصناعية',
            'contact_phone' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'warehouse.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_user_lifecycle_and_role_changes_are_audited_without_password_values(): void
    {
        $admin = User::factory()->administrator()->create();
        $repRoleId = Role::query()->where('slug', RoleSlug::SalesRepresentative->value)->value('id');
        $warehouseRoleId = Role::query()->where('slug', RoleSlug::Warehouse->value)->value('id');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'مندوب جديد',
            'email' => 'rep.audit@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role_id' => $repRoleId,
            'is_active' => '1',
        ])->assertRedirect();

        $createdLog = AuditLog::query()->where('action', 'user.created')->latest('id')->first();
        $this->assertSame($admin->id, $createdLog?->actor_user_id);
        $this->assertSame('rep.audit@example.com', data_get($createdLog->properties, 'after.email'));
        $this->assertNull(data_get($createdLog->properties, 'after.password'));
        $encoded = json_encode($createdLog->properties);
        $this->assertStringNotContainsString('Password1', (string) $encoded);

        $user = User::query()->where('email', 'rep.audit@example.com')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'مندوب محدّث',
            'email' => 'rep.audit@example.com',
            'role_id' => $warehouseRoleId,
            'is_active' => '1',
            'password' => 'Password2',
            'password_confirmation' => 'Password2',
        ])->assertRedirect();

        $updatedLog = AuditLog::query()->where('action', 'user.updated')->latest('id')->first();
        $this->assertSame($repRoleId, data_get($updatedLog->properties, 'before.role_id'));
        $this->assertSame($warehouseRoleId, data_get($updatedLog->properties, 'after.role_id'));
        $this->assertTrue(data_get($updatedLog->properties, 'password_changed'));
        $this->assertStringNotContainsString('Password2', (string) json_encode($updatedLog->properties));

        $this->actingAs($admin)->post(route('admin.users.deactivate', $user))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deactivated',
            'subject_id' => $user->id,
            'actor_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.users.activate', $user))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.activated',
            'subject_id' => $user->id,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_role_permission_and_approval_authority_changes_are_audited(): void
    {
        $admin = User::factory()->administrator()->create();
        $role = Role::query()->where('slug', RoleSlug::Warehouse->value)->with('permissions')->firstOrFail();

        $grants = [];
        foreach ($role->permissions as $existing) {
            if ($existing->slug === 'reports.export') {
                continue;
            }

            $grants[$existing->slug] = [
                'enabled' => '1',
                'scope' => $existing->pivot->scope,
            ];
        }

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), ['grants' => $grants])
            ->assertRedirect();

        $roleLog = AuditLog::query()->where('action', 'role.permissions_updated')->latest('id')->first();
        $this->assertSame($admin->id, $roleLog?->actor_user_id);
        $this->assertSame(RoleSlug::Warehouse->value, data_get($roleLog->properties, 'role_slug'));
        $this->assertArrayHasKey('reports.export', data_get($roleLog->properties, 'before', []));
        $this->assertArrayNotHasKey('reports.export', data_get($roleLog->properties, 'after', []));

        $accountsRoleId = Role::query()->where('slug', RoleSlug::Accounts->value)->value('id');
        $payload = [];
        foreach (ApprovalAuthority::query()->get() as $row) {
            $payload[$row->id] = [
                'assigned_role_id' => $row->workflow_key === 'sales_order'
                    ? $accountsRoleId
                    : $row->assigned_role_id,
            ];
        }

        $this->actingAs($admin)
            ->put(route('admin.approvals.update'), ['authorities' => $payload])
            ->assertRedirect();

        $approvalLog = AuditLog::query()->where('action', 'approval_authority.updated')->latest('id')->first();
        $this->assertSame($admin->id, $approvalLog?->actor_user_id);
        $this->assertSame($accountsRoleId, data_get($approvalLog->properties, 'after.sales_order'));
    }

    public function test_login_logout_and_password_changes_are_audited_without_secrets(): void
    {
        $user = User::factory()->administrator()->create([
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $loginLog = AuditLog::query()->where('action', 'auth.login')->latest('id')->first();
        $this->assertSame($user->id, $loginLog?->actor_user_id);
        $this->assertSame($user->id, $loginLog?->subject_id);
        $this->assertStringNotContainsString('password', strtolower((string) json_encode($loginLog->properties)));

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_user_id' => $user->id,
        ]);

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertRedirect();

        $pwLog = AuditLog::query()->where('action', 'account.password_changed')->latest('id')->first();
        $this->assertTrue(data_get($pwLog->properties, 'password_changed'));
        $this->assertStringNotContainsString('NewPass123', (string) json_encode($pwLog->properties));

        $this->post(route('logout'));

        Notification::fake();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'ResetPass1',
                'password_confirmation' => 'ResetPass1',
            ])->assertRedirect(route('login'));

            $resetLog = AuditLog::query()->where('action', 'auth.password_reset')->latest('id')->first();
            $this->assertSame($user->id, $resetLog?->actor_user_id);
            $this->assertTrue(data_get($resetLog->properties, 'password_changed'));
            $this->assertStringNotContainsString('ResetPass1', (string) json_encode($resetLog->properties));
            $this->assertStringNotContainsString($notification->token, (string) json_encode($resetLog->properties));

            return true;
        });
    }

    public function test_attribute_redactor_strips_sensitive_keys(): void
    {
        $redactor = new AttributeRedactor;

        $result = $redactor->redact([
            'email' => 'a@example.com',
            'password' => 'secret-value',
            'api_token' => 'tok_123',
            'password_changed' => true,
            'nested' => [
                'remember_token' => 'abc',
                'name' => 'ok',
            ],
        ]);

        $this->assertSame('a@example.com', $result['email']);
        $this->assertSame('[redacted]', $result['password']);
        $this->assertSame('[redacted]', $result['api_token']);
        $this->assertTrue($result['password_changed']);
        $this->assertSame('[redacted]', $result['nested']['remember_token']);
        $this->assertSame('ok', $result['nested']['name']);
    }

    public function test_audit_log_model_rejects_update_and_delete(): void
    {
        $admin = User::factory()->administrator()->create();
        $log = app(AuditLogger::class)->record('auth.login', $admin, ['email' => $admin->email], $admin);

        $this->expectException(RuntimeException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_model_rejects_delete(): void
    {
        $admin = User::factory()->administrator()->create();
        $log = app(AuditLogger::class)->record('auth.logout', $admin, [], $admin);

        $this->expectException(RuntimeException::class);
        $log->delete();
    }

    public function test_audit_index_requires_audit_view_permission(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $this->actingAs($admin)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('سجل التدقيق', false)
            ->assertSee('لا توجد أحداث بعد', false);

        $this->actingAs($manager)->get(route('admin.audit.index'))->assertOk();

        $this->actingAs($rep)->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($warehouse)->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_audit_index_filters_by_action(): void
    {
        $admin = User::factory()->administrator()->create();

        app(AuditLogger::class)->record('auth.login', $admin, ['email' => $admin->email], $admin);
        app(AuditLogger::class)->record('company.created', null, ['after' => ['name_ar' => 'سيمي']], $admin);

        $this->actingAs($admin)
            ->get(route('admin.audit.index', ['action' => 'auth.login']))
            ->assertOk()
            ->assertSee('تسجيل دخول ناجح', false)
            ->assertSee($admin->email, false)
            ->assertDontSee('سيمي', false);
    }
}
