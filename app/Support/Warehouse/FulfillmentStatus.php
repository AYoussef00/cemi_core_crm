<?php

namespace App\Support\Warehouse;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case PartiallyDelivered = 'partially_delivered';
    case FullyDelivered = 'fully_delivered';

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'لم يُسلَّم',
            self::PartiallyDelivered => 'تسليم جزئي',
            self::FullyDelivered => 'مكتمل التسليم',
        };
    }
}
