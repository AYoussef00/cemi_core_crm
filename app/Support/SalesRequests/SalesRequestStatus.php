<?php

namespace App\Support\SalesRequests;

enum SalesRequestStatus: string
{
    case Draft = 'draft';
    case AwaitingProcurement = 'awaiting_procurement';
    case ReadyForPricing = 'ready_for_pricing';
    case QuotationLinked = 'quotation_linked';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::AwaitingProcurement => 'بانتظار المشتريات',
            self::ReadyForPricing => 'جاهز للتسعير',
            self::QuotationLinked => 'مربوط بعرض سعر',
            self::Cancelled => 'ملغى',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canStartQuotation(): bool
    {
        return $this === self::ReadyForPricing;
    }
}
