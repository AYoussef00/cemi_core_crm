<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class CompanyWarehouseSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_settings_hub_shows_first_time_empty_state(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('لم يُعدَّ بعد', false)
            ->assertSee('إعداد الشركة', false)
            ->assertSee('إعداد المستودع', false)
            ->assertDontSee('شركة تجريبية', false)
            ->assertDontSee('Example Company', false);
    }

    public function test_administrator_can_create_and_update_company(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.company.edit'))
            ->assertOk()
            ->assertSee('حالة أولية فارغة', false);

        $this->actingAs($admin)
            ->put(route('admin.settings.company.update'), [
                'name_ar' => 'شركة سيمي',
                'address_ar' => 'الرياض',
                'contact_phone' => '+966500000000',
                'contact_email' => 'ops@example.com',
            ])
            ->assertRedirect(route('admin.settings.company.edit'))
            ->assertSessionHas('status_ar');

        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseHas('companies', [
            'singleton_key' => 'default',
            'name_ar' => 'شركة سيمي',
            'contact_email' => 'ops@example.com',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.settings.company.update'), [
                'name_ar' => 'شركة سيمي المحدودة',
                'address_ar' => 'جدة',
                'contact_phone' => null,
                'contact_email' => null,
            ])
            ->assertRedirect(route('admin.settings.company.edit'));

        $this->assertDatabaseCount('companies', 1);
        $this->assertSame('شركة سيمي المحدودة', Company::current()?->name_ar);
    }

    public function test_administrator_can_create_and_update_warehouse(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.warehouse.update'), [
                'name_ar' => 'المستودع الرئيسي',
                'address_ar' => 'المنطقة الصناعية',
                'contact_phone' => '+966511111111',
            ])
            ->assertRedirect(route('admin.settings.warehouse.edit'));

        $this->assertDatabaseCount('warehouses', 1);

        $this->actingAs($admin)
            ->put(route('admin.settings.warehouse.update'), [
                'name_ar' => 'مستودع الرياض',
                'address_ar' => null,
                'contact_phone' => null,
            ])
            ->assertRedirect(route('admin.settings.warehouse.edit'));

        $this->assertDatabaseCount('warehouses', 1);
        $this->assertSame('مستودع الرياض', Warehouse::current()?->name_ar);
    }

    public function test_company_name_is_required(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->from(route('admin.settings.company.edit'))
            ->put(route('admin.settings.company.update'), [
                'name_ar' => '',
                'contact_email' => 'not-an-email',
            ])
            ->assertRedirect(route('admin.settings.company.edit'))
            ->assertSessionHasErrors(['name_ar', 'contact_email']);

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_warehouse_name_is_required(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->from(route('admin.settings.warehouse.edit'))
            ->put(route('admin.settings.warehouse.update'), [
                'name_ar' => '',
            ])
            ->assertRedirect(route('admin.settings.warehouse.edit'))
            ->assertSessionHasErrors('name_ar');

        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_singleton_constraint_prevents_second_company_row(): void
    {
        Company::saveSingleton(['name_ar' => 'الشركة الأولى']);

        $this->expectException(RuntimeException::class);
        Company::query()->create([
            'singleton_key' => 'default',
            'name_ar' => 'شركة ثانية',
        ]);
    }

    public function test_singleton_constraint_prevents_second_warehouse_row(): void
    {
        Warehouse::saveSingleton(['name_ar' => 'المستودع الأول']);

        $this->expectException(RuntimeException::class);
        Warehouse::query()->create([
            'singleton_key' => 'other',
            'name_ar' => 'مستودع ثانٍ',
        ]);
    }

    public function test_save_singleton_rejects_when_any_company_already_exists_with_different_key(): void
    {
        // Bypass creating hook path that sets default key — insert raw-like via forceCreate after temporarily...
        // Simulate a stray row then ensure saveSingleton still sees count > 0.
        Company::withoutEvents(function () {
            Company::query()->create([
                'singleton_key' => 'legacy',
                'name_ar' => 'قديم',
            ]);
        });

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Company::saveSingleton(['name_ar' => 'جديد']);
    }

    public function test_non_administrator_roles_are_denied_settings_routes(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();

        foreach ([$manager, $accounts, $warehouse] as $user) {
            $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.settings.company.edit'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.settings.warehouse.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.settings.company.update'), [
                'name_ar' => 'محاولة غير مصرح بها',
            ])->assertForbidden();
            $this->actingAs($user)->put(route('admin.settings.warehouse.update'), [
                'name_ar' => 'محاولة غير مصرح بها',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
        $this->put(route('admin.settings.company.update'), ['name_ar' => 'x'])->assertRedirect(route('login'));
    }

    public function test_hub_shows_completion_after_setup(): void
    {
        $admin = User::factory()->administrator()->create();
        Company::saveSingleton(['name_ar' => 'شركة مكتملة']);
        Warehouse::saveSingleton(['name_ar' => 'مستودع مكتمل']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('مكتمل', false)
            ->assertSee('شركة مكتملة', false)
            ->assertSee('مستودع مكتمل', false)
            ->assertSee('تعديل الشركة', false)
            ->assertSee('تعديل المستودع', false);
    }

    public function test_no_vat_rate_or_branch_fields_are_accepted(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.company.update'), [
                'name_ar' => 'شركة بلا ضريبة',
                'vat_rate' => 15,
                'branch_code' => 'BR-1',
                'tax_number' => '300000000000003',
            ])
            ->assertRedirect(route('admin.settings.company.edit'));

        $company = Company::current();
        $this->assertNotNull($company);
        $this->assertFalse(isset($company->vat_rate));
        $this->assertArrayNotHasKey('vat_rate', $company->getAttributes());
        $this->assertArrayNotHasKey('branch_code', $company->getAttributes());
        $this->assertArrayNotHasKey('tax_number', $company->getAttributes());
    }
}
