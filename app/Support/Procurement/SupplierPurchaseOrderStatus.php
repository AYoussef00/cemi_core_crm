<?php

namespace App\Support\Procurement;

enum SupplierPurchaseOrderStatus: string
{
    case Open = 'open';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Open => 'مفتوح',
            self::Ordered => 'مُرسل للمورد',
            self::PartiallyReceived => 'مستلم جزئيًا',
            self::Received => 'مستلم',
            self::Cancelled => 'ملغى',
        };
    }
}
