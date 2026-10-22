<?php

namespace Tests\Unit\Sales;

use App\Models\SalesOrder;
use App\Support\Sales\SalesOrderNextAction;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Warehouse\FulfillmentStatus;
use App\Support\Warehouse\PreparationStatus;
use PHPUnit\Framework\TestCase;

class SalesOrderNextActionTest extends TestCase
{
    public function test_draft_waits_for_sales_rep_to_submit(): void
    {
        $order = new SalesOrder([
            'status' => SalesOrderStatus::Draft,
            'preparation_status' => PreparationStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Pending,
        ]);

        $next = SalesOrderNextAction::for($order);

        $this->assertSame('مندوب المبيعات', $next->waitingForAr);
        $this->assertSame('إرسال أمر البيع للاعتماد', $next->actionAr);
        $this->assertStringContainsString('مندوب المبيعات', $next->summaryAr());
    }

    public function test_pending_approval_waits_for_sales_manager(): void
    {
        $order = new SalesOrder([
            'status' => SalesOrderStatus::PendingApproval,
            'preparation_status' => PreparationStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Pending,
        ]);

        $next = SalesOrderNextAction::for($order);

        $this->assertSame('مدير المبيعات', $next->waitingForAr);
        $this->assertSame('اعتماد أو رفض أمر البيع', $next->actionAr);
    }

    public function test_approved_pending_prep_waits_for_warehouse(): void
    {
        $order = new SalesOrder([
            'status' => SalesOrderStatus::Approved,
            'preparation_status' => PreparationStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Pending,
        ]);

        $next = SalesOrderNextAction::for($order);

        $this->assertSame('المستودع', $next->waitingForAr);
        $this->assertStringContainsString('تجهيز', $next->actionAr);
    }

    public function test_fully_delivered_waits_for_accounts(): void
    {
        $order = new SalesOrder([
            'status' => SalesOrderStatus::Approved,
            'preparation_status' => PreparationStatus::Ready,
            'fulfillment_status' => FulfillmentStatus::FullyDelivered,
        ]);

        $next = SalesOrderNextAction::for($order);

        $this->assertSame('الحسابات', $next->waitingForAr);
        $this->assertStringContainsString('فاتورة', $next->actionAr);
    }
}
