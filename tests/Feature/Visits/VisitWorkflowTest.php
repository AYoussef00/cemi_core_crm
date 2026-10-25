<?php

namespace Tests\Feature\Visits;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Visits\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class VisitWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_rep_schedules_visit_for_assigned_customer_and_completes_with_outcome(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل زيارات']);

        $scheduledAt = now()->addDay()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');

        $this->actingAs($rep)->post(route('visits.store'), [
            'customer_id' => $customer->id,
            'scheduled_at' => $scheduledAt,
            'notes' => 'زيارة تعريفية',
        ])->assertRedirect();

        $visit = CustomerVisit::query()->first();
        $this->assertNotNull($visit);
        $this->assertSame($customer->id, $visit->customer_id);
        $this->assertSame($rep->id, $visit->assigned_user_id);
        $this->assertSame($rep->id, $visit->created_by_user_id);
        $this->assertSame(VisitStatus::Scheduled, $visit->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'visit.scheduled']);
        $this->assertSame(0, $rep->fresh()->notifications()->count());

        $this->actingAs($rep)->post(route('visits.complete', $visit), [
            'outcome' => 'نقاش المنتجات',
            'notes' => 'ملاحظات بعد الزيارة',
            'customer_needs' => 'عرض أسعار مواد بناء',
        ])->assertRedirect(route('visits.show', $visit).'#visit-next');

        $visit->refresh();
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertSame('نقاش المنتجات', $visit->outcome);
        $this->assertSame('عرض أسعار مواد بناء', $visit->customer_needs);
        $this->assertNotNull($visit->completed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'visit.completed']);

        $this->actingAs($rep)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('سجل الزيارات', false)
            ->assertSee('مكتملة', false);
    }

    public function test_manager_assigns_customer_rep_automatically_and_cannot_override(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create(['name' => 'مندوب العميل']);
        $otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create(['name' => 'مندوب بديل']);
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل ثابت']);

        $this->actingAs($manager)
            ->get(route('visits.create', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertSee('مندوب العميل', false)
            ->assertSee('مندوب العميل', false)
            ->assertDontSee('name="assigned_user_id"', false)
            ->assertSee('لا يمكن تغييره من هنا', false);

        $scheduledAt = now()->addDays(2)->timezone(config('app.timezone'))->format('Y-m-d\TH:i');

        $this->actingAs($manager)->post(route('visits.store'), [
            'customer_id' => $customer->id,
            'assigned_user_id' => $otherRep->id,
            'scheduled_at' => $scheduledAt,
            'notes' => 'محاولة تجاوز',
        ])->assertRedirect();

        $visit = CustomerVisit::query()->firstOrFail();
        $this->assertSame($rep->id, $visit->assigned_user_id);
        $this->assertNotSame($otherRep->id, $visit->assigned_user_id);
        $this->assertSame(1, $rep->fresh()->notifications()->count());
        $this->assertSame(0, $otherRep->fresh()->notifications()->count());
    }

    public function test_cannot_schedule_visit_when_customer_has_no_sales_rep(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->create([
            'name_ar' => 'عميل بلا مندوب',
            'assigned_sales_rep_id' => null,
        ]);

        $this->actingAs($manager)
            ->from(route('visits.create'))
            ->post(route('visits.store'), [
                'customer_id' => $customer->id,
                'scheduled_at' => now()->addDay()->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('visits.create'))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(0, CustomerVisit::query()->count());
    }

    public function test_create_reschedule_cancel_notifications_and_self_action_silence(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();

        $scheduledAt = now()->addDays(2)->timezone(config('app.timezone'))->format('Y-m-d\TH:i');

        $this->actingAs($manager)->post(route('visits.store'), [
            'customer_id' => $customer->id,
            'scheduled_at' => $scheduledAt,
        ])->assertRedirect();

        $visit = CustomerVisit::query()->firstOrFail();
        $this->assertSame($rep->id, $visit->assigned_user_id);
        $this->assertSame(1, $rep->fresh()->notifications()->count());

        $this->actingAs($manager)->put(route('visits.update', $visit), [
            'assigned_user_id' => 999999,
            'scheduled_at' => $visit->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'notes' => 'ملاحظات فقط',
        ])->assertRedirect(route('visits.show', $visit));
        $this->assertSame($rep->id, $visit->fresh()->assigned_user_id);
        $this->assertSame(1, $rep->fresh()->notifications()->count());

        $newAt = now()->addDays(5)->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->actingAs($manager)->put(route('visits.update', $visit), [
            'scheduled_at' => $newAt,
            'notes' => 'موعد جديد',
        ])->assertRedirect(route('visits.show', $visit));
        $this->assertSame(2, $rep->fresh()->notifications()->count());

        $selfAt = now()->addDays(6)->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->actingAs($rep)->put(route('visits.update', $visit), [
            'scheduled_at' => $selfAt,
            'notes' => 'تعديل ذاتي',
        ])->assertRedirect(route('visits.show', $visit));
        $this->assertSame(2, $rep->fresh()->notifications()->count());

        $this->actingAs($manager)->post(route('visits.cancel', $visit), [
            'notes' => 'إلغاء من المدير',
        ])->assertRedirect(route('visits.show', $visit));
        $this->assertSame(VisitStatus::Cancelled, $visit->fresh()->status);
        $this->assertSame(3, $rep->fresh()->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'visit.cancelled']);
    }

    public function test_rep_cancels_own_assigned_visit_without_notifying_self(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();
        $visit = CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $rep->id,
            'created_by_user_id' => $rep->id,
            'status' => VisitStatus::Scheduled,
        ]);

        $this->actingAs($rep)->post(route('visits.cancel', $visit))->assertRedirect();
        $this->assertSame(VisitStatus::Cancelled, $visit->fresh()->status);
        $this->assertSame(0, $rep->fresh()->notifications()->count());
    }

    public function test_rep_cannot_cancel_visit_not_assigned_to_them(): void
    {
        $repA = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $repB = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($repA)->create();

        // Legacy mismatched assignee row (should not occur under new policy).
        $visit = CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $repB->id,
            'created_by_user_id' => $repA->id,
            'status' => VisitStatus::Scheduled,
        ]);

        $this->actingAs($repA)->get(route('visits.show', $visit))->assertOk();
        $this->actingAs($repA)->post(route('visits.cancel', $visit))->assertForbidden();

        $this->actingAs($repB)->post(route('visits.cancel', $visit))->assertRedirect();
        $this->assertSame(VisitStatus::Cancelled, $visit->fresh()->status);
    }

    public function test_manager_cancels_any_scheduled_visit(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();
        $visit = CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $rep->id,
            'created_by_user_id' => $rep->id,
        ]);

        $this->actingAs($manager)->post(route('visits.cancel', $visit))->assertRedirect();
        $this->assertSame(VisitStatus::Cancelled, $visit->fresh()->status);
        $this->assertSame(1, $rep->fresh()->notifications()->count());
    }

    public function test_rep_cannot_view_or_manage_visits_outside_assigned_customers(): void
    {
        $repA = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $repB = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customerA = Customer::factory()->assignedTo($repA)->create();
        $customerB = Customer::factory()->assignedTo($repB)->create();

        $visitB = CustomerVisit::factory()->create([
            'customer_id' => $customerB->id,
            'assigned_user_id' => $repB->id,
            'created_by_user_id' => $repB->id,
            'status' => VisitStatus::Scheduled,
        ]);

        $this->actingAs($repA)->get(route('visits.index'))
            ->assertOk()
            ->assertDontSee($customerB->name_ar, false);

        $this->actingAs($repA)->get(route('visits.show', $visitB))->assertForbidden();
        $this->actingAs($repA)->get(route('visits.edit', $visitB))->assertForbidden();
        $this->actingAs($repA)->post(route('visits.complete', $visitB), [
            'outcome' => 'محاولة',
        ])->assertForbidden();
        $this->actingAs($repA)->post(route('visits.cancel', $visitB))->assertForbidden();

        $this->actingAs($repA)->post(route('visits.store'), [
            'customer_id' => $customerA->id,
            'scheduled_at' => now()->addDay()->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->actingAs($repA)->post(route('visits.store'), [
            'customer_id' => $customerB->id,
            'scheduled_at' => now()->addDay()->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
        ])->assertForbidden();
    }

    public function test_manager_sees_all_visits_and_warehouse_work_scope_is_empty(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل نطاق الكل']);

        CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $rep->id,
            'created_by_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)->get(route('visits.index'))
            ->assertOk()
            ->assertSee('عميل نطاق الكل', false);

        $this->actingAs($warehouse)->get(route('visits.index'))
            ->assertOk()
            ->assertSee('لا توجد زيارات', false)
            ->assertDontSee('عميل نطاق الكل', false);

        $this->actingAs($warehouse)->get(route('visits.create'))->assertForbidden();
    }

    public function test_completed_visit_cannot_be_rescheduled_or_cancelled(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();
        $visit = CustomerVisit::factory()->completed()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $rep->id,
            'created_by_user_id' => $rep->id,
        ]);

        $this->actingAs($rep)->get(route('visits.edit', $visit))->assertForbidden();
        $this->actingAs($rep)->put(route('visits.update', $visit), [
            'scheduled_at' => now()->addDays(5)->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
        ])->assertForbidden();
        $this->actingAs($rep)->post(route('visits.cancel', $visit))->assertForbidden();
        $this->actingAs($manager)->post(route('visits.cancel', $visit))->assertForbidden();
        $this->actingAs($rep)->post(route('visits.complete', $visit), [
            'outcome' => 'مرة أخرى',
        ])->assertForbidden();
    }

    public function test_list_search_and_status_filter(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'بحث الزيارات']);
        $other = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل آخر']);

        CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $rep->id,
            'created_by_user_id' => $rep->id,
            'status' => VisitStatus::Scheduled,
            'notes' => 'ملاحظة فريدة',
        ]);
        CustomerVisit::factory()->completed()->create([
            'customer_id' => $other->id,
            'assigned_user_id' => $rep->id,
            'created_by_user_id' => $rep->id,
        ]);

        $this->actingAs($rep)
            ->get(route('visits.index', ['q' => 'بحث الزيارات']))
            ->assertOk()
            ->assertSee('بحث الزيارات', false)
            ->assertDontSee('عميل آخر', false);

        $this->actingAs($rep)
            ->get(route('visits.index', ['status' => VisitStatus::Completed->value]))
            ->assertOk()
            ->assertSee('عميل آخر', false)
            ->assertDontSee('بحث الزيارات', false);
    }

    public function test_procurement_denied_visits(): void
    {
        $procurement = User::factory()->withRole(RoleSlug::Procurement)->create();

        $this->actingAs($procurement)->get(route('visits.index'))->assertForbidden();
        $this->actingAs($procurement)->get(route('visits.create'))->assertForbidden();
    }
}
