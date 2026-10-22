<?php

namespace Tests\Feature\Foundation;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Quotations\QuotationStatus;
use App\Support\Visits\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_root_is_login_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $response->assertSee('تسجيل الدخول', false);
        $response->assertDontSee('dash-home', false);
    }

    public function test_guest_home_redirects_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_login_document_is_arabic_rtl(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_legacy_login_path_redirects_to_root(): void
    {
        $this->get('/login')->assertRedirect('/');
    }

    public function test_authenticated_root_redirects_to_home(): void
    {
        $user = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('home'));
    }

    public function test_authenticated_home_shows_zero_counts_without_records(): void
    {
        $user = User::factory()->withRole(RoleSlug::SalesManager)->create([
            'name' => 'خالد الشمري',
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertViewIs('foundation.dashboard');
        $response->assertDontSee('مرحبًا، خالد الشمري', false);
        $response->assertDontSee('نظرة عامة', false);
        $response->assertSee('عروض الأسعار', false);
        $response->assertSee('أوامر البيع', false);
        $response->assertSee('الزيارات', false);
        $response->assertDontSee('dash-chart', false);
        $response->assertDontSee('يحتاج متابعة', false);
        $response->assertDontSee('بيانات معاينة', false);
        $response->assertDontSee('فاتورة', false);
        $response->assertDontSee('مديون', false);
    }

    public function test_authenticated_home_shows_real_quotation_and_visit_metrics(): void
    {
        $user = User::factory()->withRole(RoleSlug::SalesManager)->create([
            'name' => 'نورة',
        ]);
        $customer = Customer::factory()->create([
            'assigned_sales_rep_id' => User::factory()->withRole(RoleSlug::SalesRepresentative)->create()->id,
        ]);

        Quotation::factory()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'status' => QuotationStatus::Sent,
        ]);

        CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $customer->assigned_sales_rep_id,
            'created_by_user_id' => $user->id,
            'status' => VisitStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('عروض الأسعار', false);
        $response->assertSee('الزيارات', false);
        $response->assertSee('dash-chart', false);
        $response->assertSee('عروض الأسعار حسب الحالة', false);
        $response->assertSee('الزيارات أسبوعيًا', false);
        $response->assertDontSee('بيانات معاينة', false);
    }

    public function test_home_dashboard_scopes_rep_to_own_customers_manager_and_admin_see_all(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create(['name' => 'مندوب أ']);
        $otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create(['name' => 'مندوب ب']);
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create(['name' => 'مدير']);
        $admin = User::factory()->withRole(RoleSlug::Administrator)->create(['name' => 'إدمن']);

        $mine = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'منشأتي']);
        $theirs = Customer::factory()->assignedTo($otherRep)->create(['name_ar' => 'منشأتهم']);

        Quotation::factory()->create([
            'customer_id' => $mine->id,
            'created_by_user_id' => $rep->id,
            'status' => QuotationStatus::Sent,
        ]);
        Quotation::factory()->create([
            'customer_id' => $theirs->id,
            'created_by_user_id' => $otherRep->id,
            'status' => QuotationStatus::Sent,
        ]);

        $this->actingAs($rep)->get(route('home'))->assertOk()->assertSee('عروض الأسعار', false);
        $this->actingAs($manager)->get(route('home'))->assertOk();
        $this->actingAs($admin)->get(route('home'))->assertOk();

        $builder = app(\App\Support\Dashboard\HomeDashboardBuilder::class);
        $repQuotes = collect($builder->for($rep)['kpis'])->firstWhere('key', 'quotations')['value'] ?? 0;
        $managerQuotes = collect($builder->for($manager)['kpis'])->firstWhere('key', 'quotations')['value'] ?? 0;
        $adminQuotes = collect($builder->for($admin)['kpis'])->firstWhere('key', 'quotations')['value'] ?? 0;

        $this->assertSame(1, $repQuotes);
        $this->assertSame(2, $managerQuotes);
        $this->assertSame(2, $adminQuotes);
    }
}
