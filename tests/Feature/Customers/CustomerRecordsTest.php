<?php

namespace Tests\Feature\Customers;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
use App\Models\Deal;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerContactRole;
use App\Support\Finance\CollectionStatus;
use App\Support\Finance\InvoiceStatus;
use App\Support\Sales\DealStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class CustomerRecordsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_sales_manager_can_create_customer_with_contacts_and_audit(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        $response = $this->actingAs($manager)->post(route('customers.store'), [
            'name_ar' => 'شركة النور',
            'assigned_sales_rep_id' => $rep->id,
            'contacts' => [
                [
                    'role' => CustomerContactRole::Account->value,
                    'name_ar' => 'أحمد الحساب',
                    'phone' => '0500000001',
                    'email' => 'account@example.com',
                ],
                [
                    'role' => CustomerContactRole::Accounting->value,
                    'name_ar' => 'سارة المحاسبة',
                    'phone' => '0500000002',
                    'email' => 'accounting@example.com',
                ],
            ],
        ]);

        $customer = Customer::query()->where('name_ar', 'شركة النور')->first();
        $this->assertNotNull($customer);
        $response->assertRedirect(route('customers.show', $customer));

        $this->assertSame($rep->id, $customer->assigned_sales_rep_id);
        $this->assertDatabaseCount('customer_contacts', 2);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customer->id,
            'role' => CustomerContactRole::Account->value,
            'name_ar' => 'أحمد الحساب',
        ]);

        $log = AuditLog::query()->where('action', 'customer.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($manager->id, $log->actor_user_id);
        $this->assertSame($customer->id, $log->subject_id);
        $this->assertSame('شركة النور', $log->properties['name_ar'] ?? null);
        $this->assertSame(2, $log->properties['contacts_count'] ?? null);
    }

    public function test_sales_representative_create_assigns_self_and_can_view_update(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        $response = $this->actingAs($rep)->post(route('customers.store'), [
            'name_ar' => 'عميل المندوب',
            'contacts' => [
                [
                    'role' => CustomerContactRole::Account->value,
                    'name_ar' => 'جهة اتصال',
                    'phone' => null,
                    'email' => null,
                ],
            ],
        ]);

        $customer = Customer::query()->where('name_ar', 'عميل المندوب')->first();
        $this->assertNotNull($customer);
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame($rep->id, $customer->assigned_sales_rep_id);
        $response->assertRedirect(route('customers.show', $customer));

        $this->actingAs($rep)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('عميل المندوب', false)
            ->assertSee('جهة اتصال', false);

        $this->actingAs($rep)
            ->put(route('customers.update', $customer), [
                'name_ar' => 'عميل المندوب المحدّث',
                'contacts' => [
                    [
                        'role' => CustomerContactRole::Accounting->value,
                        'name_ar' => 'محاسب محدّث',
                        'phone' => '0511111111',
                        'email' => 'acc@example.com',
                    ],
                ],
            ])
            ->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertSame('عميل المندوب المحدّث', $customer->name_ar);
        $this->assertSame($rep->id, $customer->assigned_sales_rep_id);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customer->id,
            'role' => CustomerContactRole::Accounting->value,
            'name_ar' => 'محاسب محدّث',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated']);
    }

    public function test_sales_representative_denied_other_reps_customer(): void
    {
        $repA = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $repB = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($repA)->create(['name_ar' => 'عميل أ']);

        $this->actingAs($repB)
            ->get(route('customers.show', $customer))
            ->assertForbidden();

        $this->actingAs($repB)
            ->get(route('customers.edit', $customer))
            ->assertForbidden();

        $this->actingAs($repB)
            ->put(route('customers.update', $customer), [
                'name_ar' => 'محاولة تعديل',
                'contacts' => [],
            ])
            ->assertForbidden();

        $this->actingAs($repB)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('عميل أ', false);
    }

    public function test_sales_manager_sees_all_customers(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل مرئي للمدير']);

        $this->actingAs($manager)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('عميل مرئي للمدير', false);
    }

    public function test_role_matrix_view_and_manage_denials(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل صلاحيات']);

        $procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $this->actingAs($procurement)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($procurement)->get(route('customers.show', $customer))->assertForbidden();
        $this->actingAs($procurement)->get(route('customers.create'))->assertForbidden();

        $executive = User::factory()->withRole(RoleSlug::Executive)->create();
        $this->actingAs($executive)->get(route('customers.index'))->assertOk();
        $this->actingAs($executive)->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($executive)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($executive)->get(route('customers.edit', $customer))->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get(route('customers.index'))->assertOk();
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($admin)->get(route('customers.create'))->assertForbidden();

        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->actingAs($warehouse)->get(route('customers.index'))->assertOk();
        $this->actingAs($warehouse)->get(route('customers.show', $customer))->assertForbidden();
        $this->actingAs($warehouse)->get(route('customers.create'))->assertForbidden();
    }

    public function test_validation_requires_customer_name_and_valid_contact_email(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->actingAs($manager)
            ->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name_ar' => '',
                'contacts' => [
                    [
                        'role' => CustomerContactRole::Account->value,
                        'name_ar' => 'اسم',
                        'email' => 'not-an-email',
                    ],
                ],
            ])
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['name_ar', 'contacts.0.email']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_list_search_matches_name_and_contact_fields(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $match = Customer::factory()->create(['name_ar' => 'مؤسسة البحث']);
        CustomerContact::query()->create([
            'customer_id' => $match->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'خالد',
            'phone' => '0599999999',
            'email' => 'khaled@search.test',
        ]);
        Customer::factory()->create(['name_ar' => 'عميل آخر']);

        $this->actingAs($manager)
            ->get(route('customers.index', ['q' => 'مؤسسة']))
            ->assertOk()
            ->assertSee('مؤسسة البحث', false)
            ->assertDontSee('عميل آخر', false);

        $this->actingAs($manager)
            ->get(route('customers.index', ['q' => '0599999999']))
            ->assertOk()
            ->assertSee('مؤسسة البحث', false);

        $this->actingAs($manager)
            ->get(route('customers.index', ['q' => 'khaled@search.test']))
            ->assertOk()
            ->assertSee('مؤسسة البحث', false);
    }

    public function test_guest_redirected_from_customers(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_empty_state_when_no_customers_in_scope(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        $this->actingAs($rep)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('لا توجد منشآت ضمن نطاق صلاحيتك بعد', false);
    }

    public function test_customer_forms_use_establishment_name_label_for_org_only_v1(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->create(['name_ar' => 'منشأة العرض']);

        $this->actingAs($manager)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('اسم المنشأة', false)
            ->assertSee('منشأة/مؤسسة تجارية فقط', false)
            ->assertDontSee('>اسم العميل<', false);

        $this->actingAs($manager)
            ->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('اسم المنشأة', false);

        $this->actingAs($manager)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('اسم المنشأة', false)
            ->assertSee('منشأة العرض', false);

        $this->actingAs($manager)
            ->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name_ar' => '',
                'contacts' => [],
            ])
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors('name_ar');

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('اسم المنشأة', $errors->first('name_ar'));
    }

    public function test_customer_profile_shows_commercial_hub_records(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'منشأة الملف التجاري']);

        $salesRequest = SalesRequest::factory()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $rep->id,
        ]);
        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'sales_request_id' => $salesRequest->id,
            'created_by_user_id' => $rep->id,
        ]);
        $po = CustomerPurchaseOrder::factory()->create([
            'customer_id' => $customer->id,
            'quotation_id' => $quotation->id,
            'created_by_user_id' => $rep->id,
            'reference_ar' => 'PO-HUB-1',
        ]);
        $deal = Deal::query()->create([
            'customer_id' => $customer->id,
            'customer_purchase_order_id' => $po->id,
            'status' => DealStatus::Open,
        ]);
        $salesOrder = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'customer_purchase_order_id' => $po->id,
            'deal_id' => $deal->id,
            'created_by_user_id' => $rep->id,
        ]);
        $delivery = Delivery::factory()->create([
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'delivery_id' => $delivery->id,
            'deal_id' => $deal->id,
            'status' => InvoiceStatus::Issued,
            'total_vat_inclusive' => '1500.00',
            'due_at' => now()->subDay(),
        ]);
        Collection::factory()->create([
            'customer_id' => $customer->id,
            'status' => CollectionStatus::Draft,
            'amount_total' => '200.00',
        ]);

        $this->actingAs($manager)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('الملف التجاري', false)
            ->assertSee('عروض الأسعار', false)
            ->assertSee('أوامر الشراء', false)
            ->assertSee('أوامر البيع', false)
            ->assertSee('التسليمات', false)
            ->assertSee('مستحقات', false)
            ->assertSee('الفواتير', false)
            ->assertSee('التحصيلات', false)
            ->assertSee('PO-HUB-1', false)
            ->assertSee('#'.$quotation->id, false)
            ->assertSee('#'.$invoice->id, false)
            ->assertSee('متأخر', false);
    }
}
