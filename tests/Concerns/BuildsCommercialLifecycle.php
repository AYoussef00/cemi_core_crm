<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerContactRole;
use App\Support\SalesRequests\LineAvailability;
use App\Support\Warehouse\DeliveryStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared HTTP helpers for Phase 10 commercial lifecycle scenarios.
 */
trait BuildsCommercialLifecycle
{
    protected User $rep;

    protected User $manager;

    protected User $accounts;

    protected User $warehouseUser;

    protected User $procurement;

    protected Customer $customer;

    protected Product $product;

    protected function bootCommercialLifecycle(array $customerExtra = [], string $onHand = '200.000'): void
    {
        Storage::fake((string) config('attachments.disk', 'attachments'));
        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $this->warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->procurement = User::factory()->withRole(RoleSlug::Procurement)->create();

        $this->customer = Customer::factory()->assignedTo($this->rep)->create(array_merge([
            'name_ar' => 'منشأة دورة كاملة',
            'approved_credit_limit' => '50000.00',
        ], $customerExtra));

        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة حسابات',
            'email' => 'pilot@example.test',
            'phone' => null,
        ]);

        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج دورة',
            'unit_id' => $unit->id,
            'purchase_cost' => '100.00',
        ]);

        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $this->product->id,
            'on_hand' => $onHand,
            'reserved' => '0.000',
        ]);
    }

    protected function scheduleAndCompleteVisit(): void
    {
        $scheduledAt = now()->addDay()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');

        $this->actingAs($this->rep)->post(route('visits.store'), [
            'customer_id' => $this->customer->id,
            'scheduled_at' => $scheduledAt,
            'notes' => 'زيارة تجريبية',
        ])->assertRedirect();

        $visit = $this->customer->fresh()->visits()->latest('id')->firstOrFail();

        $this->actingAs($this->rep)->post(route('visits.complete', $visit), [
            'outcome' => 'طلب توريد',
            'notes' => 'تم',
            'customer_needs' => 'مواد',
        ])->assertRedirect();
    }

    /**
     * Available path → approved quotation (margin 10 → selling 110 VAT-incl).
     */
    protected function createApprovedQuotation(string $qty = '2', string $margin = '10'): Quotation
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => $qty,
                'availability' => LineAvailability::Available->value,
            ]],
        ])->assertRedirect();

        $request = SalesRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request))->assertRedirect();

        // Draft quotation auto-opens for SM when request becomes ready_for_pricing.
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->latest('id')->firstOrFail();
        $qLine = $quotation->lines()->firstOrFail();

        // PM1/PM2: Sales Manager sets margin then finalizes for the Rep.
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'expires_at' => now()->addDays(14)->format('Y-m-d\TH:i'),
            'lines' => [['id' => $qLine->id, 'margin_amount' => $margin]],
        ])->assertRedirect();

        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ])->assertRedirect();

        return $quotation->fresh(['lines']);
    }

    protected function createPoAndApprovedSalesOrder(Quotation $quotation, string $qty): SalesOrder
    {
        $qLine = $quotation->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => $qty]],
        ])->assertRedirect();

        $po = CustomerPurchaseOrder::query()->latest('id')->firstOrFail();
        $poLine = $po->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => $qty]],
        ])->assertRedirect();

        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $order))->assertRedirect();
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ])->assertRedirect();

        return $order->fresh(['lines', 'deal']);
    }

    protected function createSecondSalesOrderFromPo(CustomerPurchaseOrder $po, string $qty): SalesOrder
    {
        $poLine = $po->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => $qty]],
        ])->assertRedirect();

        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $order))->assertRedirect();
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ])->assertRedirect();

        return $order->fresh(['lines']);
    }

    protected function confirmDelivery(SalesOrder $order, string $qty): Delivery
    {
        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => $qty]],
        ])->assertRedirect();

        $delivery = Delivery::query()->latest('id')->firstOrFail();
        $file = UploadedFile::fake()->create('pod.pdf', 40, 'application/pdf');
        $this->actingAs($this->warehouseUser)
            ->post(route('deliveries.pod.store', $delivery), ['file' => $file])
            ->assertRedirect();
        $this->actingAs($this->warehouseUser)
            ->post(route('deliveries.confirm', $delivery))
            ->assertRedirect();

        $this->assertSame(DeliveryStatus::Confirmed, $delivery->fresh()->status);

        return $delivery->fresh();
    }

    protected function issueInvoice(Delivery $delivery): \App\Models\Invoice
    {
        $existing = \App\Models\Invoice::query()->where('delivery_id', $delivery->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $this->actingAs($this->accounts)->post(route('invoices.store'), [
            'delivery_id' => $delivery->id,
        ])->assertRedirect();

        return \App\Models\Invoice::query()->latest('id')->firstOrFail();
    }
}
