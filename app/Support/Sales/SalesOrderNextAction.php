<?php

namespace App\Support\Sales;

use App\Models\SalesOrder;
use App\Support\Warehouse\DeliveryStatus;
use App\Support\Warehouse\FulfillmentStatus;
use App\Support\Warehouse\PreparationStatus;

/**
 * Honest next-step copy for SO list/show: who is waited on, and what they must do.
 * Derived only from existing SO / delivery statuses — no invented workflow states.
 */
final class SalesOrderNextAction
{
    public function __construct(
        public readonly string $waitingForAr,
        public readonly string $actionAr,
        public readonly string $badge,
        public readonly bool $isTerminal = false,
    ) {}

    public function summaryAr(): string
    {
        if ($this->isTerminal) {
            return $this->actionAr;
        }

        return 'في انتظار '.$this->waitingForAr;
    }

    public static function for(SalesOrder $order): self
    {
        return match ($order->status) {
            SalesOrderStatus::Draft => new self(
                waitingForAr: 'مندوب المبيعات',
                actionAr: 'إرسال أمر البيع للاعتماد',
                badge: 'badge-neutral',
            ),
            SalesOrderStatus::PendingApproval => new self(
                waitingForAr: 'مدير المبيعات',
                actionAr: 'اعتماد أو رفض أمر البيع',
                badge: 'badge-warning',
            ),
            SalesOrderStatus::Rejected => new self(
                waitingForAr: '—',
                actionAr: 'مرفوض — لا خطوة تالية على هذا الأمر',
                badge: 'badge-danger',
                isTerminal: true,
            ),
            SalesOrderStatus::Cancelled => new self(
                waitingForAr: '—',
                actionAr: 'ملغى — لا خطوة تالية',
                badge: 'badge-danger',
                isTerminal: true,
            ),
            SalesOrderStatus::Approved => self::forApproved($order),
        };
    }

    private static function forApproved(SalesOrder $order): self
    {
        if ($order->fulfillment_status === FulfillmentStatus::FullyDelivered) {
            return new self(
                waitingForAr: 'الحسابات',
                actionAr: 'إصدار الفاتورة ثم التحصيل وإغلاق الصفقة عند السداد',
                badge: 'badge-success',
            );
        }

        if ($order->preparation_status !== PreparationStatus::Ready) {
            return new self(
                waitingForAr: 'المستودع (تجهيز)',
                actionAr: 'تجهيز الكمية المحجوزة وتعليم جاهز للتسليم ثم إنشاء أمر تسليم للعميل',
                badge: 'badge-warning',
            );
        }

        $openStatus = self::openDeliveryHint($order);
        if ($openStatus === DeliveryStatus::PendingPod) {
            return new self(
                waitingForAr: 'المستودع',
                actionAr: 'رفع إثبات التسليم (POD) وتأكيد التسليم',
                badge: 'badge-warning',
            );
        }

        if ($openStatus === DeliveryStatus::Draft) {
            return new self(
                waitingForAr: 'المستودع',
                actionAr: 'إكمال مسودة التسليم ورفع إثبات الاستلام ثم التأكيد',
                badge: 'badge-warning',
            );
        }

        if ($order->fulfillment_status === FulfillmentStatus::PartiallyDelivered) {
            return new self(
                waitingForAr: 'المستودع',
                actionAr: 'إنشاء تسليم للكمية المتبقية وتأكيده بعد إثبات الاستلام',
                badge: 'badge-warning',
            );
        }

        return new self(
            waitingForAr: 'المستودع',
            actionAr: 'إنشاء أمر تسليم ورفع إثبات الاستلام ثم التأكيد',
            badge: 'badge-warning',
        );
    }

    private static function openDeliveryHint(SalesOrder $order): ?DeliveryStatus
    {
        if (isset($order->pending_pod_deliveries_count) || isset($order->draft_deliveries_count)) {
            if ((int) ($order->pending_pod_deliveries_count ?? 0) > 0) {
                return DeliveryStatus::PendingPod;
            }
            if ((int) ($order->draft_deliveries_count ?? 0) > 0) {
                return DeliveryStatus::Draft;
            }

            return null;
        }

        if ($order->relationLoaded('deliveries')) {
            if ($order->deliveries->contains(fn ($d) => $d->status === DeliveryStatus::PendingPod)) {
                return DeliveryStatus::PendingPod;
            }
            if ($order->deliveries->contains(fn ($d) => $d->status === DeliveryStatus::Draft)) {
                return DeliveryStatus::Draft;
            }
        }

        return null;
    }
}
