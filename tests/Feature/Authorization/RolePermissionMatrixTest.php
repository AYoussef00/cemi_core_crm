<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Support\Authorization\RecordScope;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Authorization\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_sales_representative_key_allows_and_denies(): void
    {
        $user = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        $this->assertTrue($user->can('sales_orders.create_draft'));
        $this->assertTrue($user->can('sales_orders.submit'));
        $this->assertTrue($user->can('credit_limit.request'));
        $this->assertFalse($user->can('sales_orders.approve'));
        $this->assertFalse($user->can('sales_orders.cancel_approved'));
        $this->assertFalse($user->can('quotations.approve_price'));
        $this->assertFalse($user->can('users.manage'));
        $this->assertFalse($user->can('credit_limit.approve'));
        $this->assertSame(RecordScope::Cust, $user->permissionScope('sales_orders.view'));
        $this->assertSame(RecordScope::Assigned, $user->permissionScope('customers.view'));
        $this->assertSame(RecordScope::Cust, $user->permissionScope('quotations.view'));
        $this->assertSame(RecordScope::Cust, $user->permissionScope('invoices.view'));
    }

    public function test_sales_manager_key_allows_and_denies(): void
    {
        $user = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->assertTrue($user->can('sales_orders.approve'));
        $this->assertTrue($user->can('sales_orders.cancel_approved'));
        $this->assertTrue($user->can('quotations.approve_price'));
        $this->assertTrue($user->can('inventory_adjustment.approve'));
        $this->assertTrue($user->can('credit_limit.approve'));
        $this->assertTrue($user->can('credit_notes.approve'));
        $this->assertTrue($user->can('collection_reversal.approve'));
        $this->assertFalse($user->can('users.manage'));
        $this->assertFalse($user->can('inventory_adjustment.record'));
        $this->assertFalse($user->can('invoices.issue'));
        $this->assertSame(RecordScope::All, $user->permissionScope('sales_orders.view'));
        $this->assertSame(RecordScope::All, $user->permissionScope('quotations.view'));
        $this->assertSame(RecordScope::All, $user->permissionScope('invoices.view'));
    }

    public function test_procurement_purchase_cost_only_and_referred_scope(): void
    {
        $user = User::factory()->withRole(RoleSlug::Procurement)->create();

        $this->assertTrue($user->can('products.edit_purchase_cost'));
        $this->assertFalse($user->can('products.manage'));
        $this->assertFalse($user->can('prices.view'));
        $this->assertFalse($user->can('invoices.view'));
        $this->assertFalse($user->can('sales_orders.approve'));
        $this->assertSame(RecordScope::Referred, $user->permissionScope('sales_orders.view'));
    }

    public function test_warehouse_cannot_view_prices_or_invoices(): void
    {
        $user = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $this->assertTrue($user->can('inventory_adjustment.record'));
        $this->assertTrue($user->can('delivery.manage'));
        $this->assertTrue($user->can('sales_orders.view'));
        $this->assertFalse($user->can('prices.view'));
        $this->assertFalse($user->can('invoices.view'));
        $this->assertFalse($user->can('inventory_adjustment.approve'));
        $this->assertFalse($user->can('sales_orders.cancel_approved'));
        $this->assertSame(RecordScope::All, $user->permissionScope('sales_orders.view'));
        $this->assertSame(RecordScope::Work, $user->permissionScope('customers.view'));
    }

    public function test_accounts_finance_actions_without_sales_approvals(): void
    {
        $user = User::factory()->withRole(RoleSlug::Accounts)->create();

        $this->assertTrue($user->can('invoices.issue'));
        $this->assertTrue($user->can('collections.record'));
        $this->assertTrue($user->can('credit_notes.create'));
        $this->assertTrue($user->can('collection_reversal.request'));
        $this->assertTrue($user->can('credit_limit.review'));
        $this->assertTrue($user->can('deals.close'));
        $this->assertTrue($user->can('overdue.resend'));
        $this->assertFalse($user->can('credit_notes.approve'));
        $this->assertFalse($user->can('collection_reversal.approve'));
        $this->assertFalse($user->can('credit_limit.approve'));
        $this->assertFalse($user->can('sales_orders.approve'));
        $this->assertSame(RecordScope::All, $user->permissionScope('invoices.view'));
        $this->assertSame(RecordScope::All, $user->permissionScope('sales_orders.view'));
    }

    public function test_executive_is_read_only_without_sales_approvals(): void
    {
        $user = User::factory()->withRole(RoleSlug::Executive)->create();

        $this->assertTrue($user->can('reports.operational'));
        $this->assertTrue($user->can('reports.financial'));
        $this->assertTrue($user->can('quotations.view'));
        $this->assertTrue($user->can('delivery.view'));
        $this->assertFalse($user->can('delivery.manage'));
        $this->assertFalse($user->can('sales_orders.approve'));
        $this->assertFalse($user->can('quotations.approve_price'));
        $this->assertFalse($user->can('users.manage'));
        $this->assertFalse($user->can('customers.manage'));
        $this->assertSame(RecordScope::All, $user->permissionScope('sales_orders.view'));
    }

    public function test_administrator_manages_system_without_sales_approval_powers(): void
    {
        $user = User::factory()->administrator()->create();

        $this->assertTrue($user->can('users.manage'));
        $this->assertTrue($user->can('roles.manage'));
        $this->assertTrue($user->can('approval_authority.configure'));
        $this->assertTrue($user->can('settings.manage'));
        $this->assertTrue($user->can('products.manage'));
        $this->assertFalse($user->can('sales_orders.approve'));
        $this->assertFalse($user->can('sales_orders.cancel_approved'));
        $this->assertFalse($user->can('quotations.approve_price'));
        $this->assertFalse($user->can('credit_limit.approve'));
        $this->assertFalse($user->can('credit_notes.approve'));
        $this->assertFalse($user->can('collection_reversal.approve'));
        $this->assertFalse($user->can('inventory_adjustment.approve'));
        $this->assertFalse($user->canApproveWorkflow('sales_order'));
        $this->assertSame(RecordScope::Admin, $user->permissionScope('sales_orders.view'));
    }

    public function test_record_scope_resolver_enforces_own_and_assigned_rules(): void
    {
        $resolver = app(RecordScopeResolver::class);
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $other = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->assertTrue($resolver->allowsRecord($rep, 'sales_orders.view', [
            'assigned_sales_rep_id' => $rep->id,
        ]));
        $this->assertFalse($resolver->allowsRecord($rep, 'sales_orders.view', [
            'assigned_sales_rep_id' => $other->id,
        ]));
        $this->assertTrue($resolver->allowsRecord($manager, 'sales_orders.view', [
            'assigned_sales_rep_id' => $other->id,
        ]));

        $this->assertTrue($resolver->allowsRecord($rep, 'customers.view', [
            'assigned_sales_rep_id' => $rep->id,
        ]));
        $this->assertFalse($resolver->allowsRecord($rep, 'customers.view', [
            'assigned_sales_rep_id' => $other->id,
        ]));
    }

    public function test_warehouse_and_procurement_record_scopes(): void
    {
        $resolver = app(RecordScopeResolver::class);
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $procurement = User::factory()->withRole(RoleSlug::Procurement)->create();

        $this->assertFalse($resolver->allowsRecord($warehouse, 'customers.view', [
            'is_work_related' => false,
        ]));
        $this->assertTrue($resolver->allowsRecord($warehouse, 'customers.view', [
            'is_work_related' => true,
        ]));

        $this->assertFalse($resolver->allowsRecord($procurement, 'sales_orders.view', [
            'is_referred_to_procurement' => false,
        ]));
        $this->assertTrue($resolver->allowsRecord($procurement, 'sales_orders.view', [
            'is_referred_to_procurement' => true,
        ]));
    }

    public function test_approval_gates_follow_configured_role_and_permission(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $admin = User::factory()->administrator()->create();

        $this->assertTrue($manager->can('approve.sales_order'));
        $this->assertTrue($manager->can('approve.credit_debit_note'));
        $this->assertTrue($accounts->can('approve.credit_limit_review'));
        $this->assertFalse($accounts->can('approve.credit_limit_final'));
        $this->assertFalse($admin->can('approve.sales_order'));
        $this->assertFalse($admin->can('approve.inventory_adjustment'));
    }
}
