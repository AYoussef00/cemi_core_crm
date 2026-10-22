<?php

namespace Tests\Feature\Credit;

use App\Models\AuditLog;
use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Credit\CreditLimitRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class CreditLimitWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_rep_requests_accounts_forwards_sm_approves_and_activates_values(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->assignedTo($rep)->create([
            'name_ar' => 'عميل ائتمان',
            'approved_credit_limit' => null,
            'approved_payment_term' => null,
        ]);

        $this->actingAs($rep)->post(route('customers.credit-requests.store', $customer), [
            'requested_credit_limit' => '25000.50',
            'requested_payment_term' => '45 يوم',
            'request_notes' => 'طلب أولي',
        ])->assertRedirect();

        $request = CreditLimitRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame(CreditLimitRequestStatus::PendingReview, $request->status);
        $this->assertNull($customer->fresh()->approved_credit_limit);

        $this->assertDatabaseHas('audit_logs', ['action' => 'credit_limit.requested']);
        $this->assertSame(1, $accounts->fresh()->notifications()->count());

        $this->actingAs($accounts)->post(route('credit-requests.review', $request), [
            'decision' => 'forward',
            'notes' => 'موافق للمراجعة',
        ])->assertRedirect(route('credit-requests.show', $request));

        $request->refresh();
        $this->assertSame(CreditLimitRequestStatus::PendingFinal, $request->status);
        $this->assertNull($customer->fresh()->approved_credit_limit);
        $this->assertSame(1, $manager->fresh()->notifications()->count());

        $this->actingAs($manager)->post(route('credit-requests.decide', $request), [
            'decision' => 'approve',
            'notes' => 'معتمد',
        ])->assertRedirect(route('credit-requests.show', $request));

        $request->refresh();
        $customer->refresh();
        $this->assertSame(CreditLimitRequestStatus::Approved, $request->status);
        $this->assertSame('25000.50', (string) $customer->approved_credit_limit);
        $this->assertSame('45 يوم', $customer->approved_payment_term);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credit_limit.approved']);
        $this->assertGreaterThanOrEqual(1, $rep->fresh()->notifications()->count());
    }

    public function test_requested_values_do_not_apply_on_review_reject_or_final_reject(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->assignedTo($rep)->create([
            'approved_credit_limit' => '5000.00',
            'approved_payment_term' => 'نقدي',
        ]);

        $this->actingAs($rep)->post(route('customers.credit-requests.store', $customer), [
            'requested_credit_limit' => '90000',
            'requested_payment_term' => '90 يوم',
        ]);
        $request = CreditLimitRequest::query()->firstOrFail();

        $this->actingAs($accounts)->post(route('credit-requests.review', $request), [
            'decision' => 'reject',
            'notes' => 'مرفوض',
        ])->assertRedirect();

        $customer->refresh();
        $this->assertSame('5000.00', (string) $customer->approved_credit_limit);
        $this->assertSame('نقدي', $customer->approved_payment_term);
        $this->assertSame(CreditLimitRequestStatus::Rejected, $request->fresh()->status);

        $this->actingAs($rep)->post(route('customers.credit-requests.store', $customer), [
            'requested_credit_limit' => '80000',
            'requested_payment_term' => '60 يوم',
        ]);
        $second = CreditLimitRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($accounts)->post(route('credit-requests.review', $second), [
            'decision' => 'forward',
        ]);
        $this->actingAs($manager)->post(route('credit-requests.decide', $second), [
            'decision' => 'reject',
        ]);

        $customer->refresh();
        $this->assertSame('5000.00', (string) $customer->approved_credit_limit);
        $this->assertSame('نقدي', $customer->approved_payment_term);
        $this->assertSame(CreditLimitRequestStatus::Rejected, $second->fresh()->status);
    }

    public function test_rep_cannot_request_for_other_reps_customer(): void
    {
        $repA = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $repB = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($repA)->create();

        $this->actingAs($repB)
            ->post(route('customers.credit-requests.store', $customer), [
                'requested_credit_limit' => '1000',
            ])
            ->assertForbidden();

        $this->actingAs($repB)
            ->get(route('customers.credit.show', $customer))
            ->assertForbidden();
    }

    public function test_prohibited_transitions_and_role_denials(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();

        $this->actingAs($rep)->post(route('customers.credit-requests.store', $customer), [
            'requested_credit_limit' => '1000',
        ]);
        $request = CreditLimitRequest::query()->firstOrFail();

        // SM cannot skip Accounts review
        $this->actingAs($manager)
            ->post(route('credit-requests.decide', $request), ['decision' => 'approve'])
            ->assertForbidden();

        // Rep cannot review
        $this->actingAs($rep)
            ->post(route('credit-requests.review', $request), ['decision' => 'forward'])
            ->assertForbidden();

        // Warehouse cannot view credit queue
        $this->actingAs($warehouse)
            ->get(route('credit-requests.index'))
            ->assertForbidden();

        $this->actingAs($accounts)->post(route('credit-requests.review', $request), [
            'decision' => 'forward',
        ]);

        // Accounts cannot final-approve
        $this->actingAs($accounts)
            ->post(route('credit-requests.decide', $request), ['decision' => 'approve'])
            ->assertForbidden();

        // Cannot re-review after forward
        $this->actingAs($accounts)
            ->post(route('credit-requests.review', $request), ['decision' => 'reject'])
            ->assertForbidden();
    }

    public function test_cannot_open_second_request_while_one_is_pending(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();

        $this->actingAs($rep)->post(route('customers.credit-requests.store', $customer), [
            'requested_credit_limit' => '1000',
        ])->assertRedirect();

        $this->actingAs($rep)
            ->from(route('customers.credit.show', $customer))
            ->post(route('customers.credit-requests.store', $customer), [
                'requested_credit_limit' => '2000',
            ])
            ->assertRedirect(route('customers.credit.show', $customer))
            ->assertSessionHasErrors('requested_credit_limit');

        $this->assertSame(1, CreditLimitRequest::query()->count());
    }

    public function test_validation_requires_credit_limit(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();

        $this->actingAs($rep)
            ->from(route('customers.credit.show', $customer))
            ->post(route('customers.credit-requests.store', $customer), [
                'requested_credit_limit' => '',
            ])
            ->assertSessionHasErrors('requested_credit_limit');
    }

    public function test_profile_separates_requested_from_approved_on_screen(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create([
            'approved_credit_limit' => '1000.00',
            'approved_payment_term' => 'نقدي',
        ]);

        $this->actingAs($rep)->post(route('customers.credit-requests.store', $customer), [
            'requested_credit_limit' => '7777.00',
            'requested_payment_term' => 'آجل 60',
        ]);

        $this->actingAs($rep)
            ->get(route('customers.credit.show', $customer))
            ->assertOk()
            ->assertSee('حد الائتمان المعتمد', false)
            ->assertSee('1,000.00', false)
            ->assertSee('نقدي', false)
            ->assertSee('غير محسوب بعد', false)
            ->assertSee('7,777.00', false); // appears in request history only

        $this->assertSame('1000.00', (string) $customer->fresh()->approved_credit_limit);
        $this->assertNotSame('7777.00', (string) $customer->fresh()->approved_credit_limit);

        $request = CreditLimitRequest::query()->firstOrFail();
        $this->actingAs($rep)
            ->get(route('credit-requests.show', $request))
            ->assertOk()
            ->assertSee('7,777.00', false)
            ->assertSee('1,000.00', false)
            ->assertSee('ليست فعّالة حتى الاعتماد النهائي', false);
    }
}
