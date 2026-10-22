<?php

namespace App\Support\Inventory;

enum InventoryLedgerEventType: string
{
    case Adjustment = 'adjustment';
    case Reserve = 'reserve';
    case Deduct = 'deduct';
    case Release = 'release';
    case Receipt = 'receipt';

    public function labelAr(): string
    {
        return match ($this) {
            self::Adjustment => 'تسوية مخزون',
            self::Reserve => 'حجز',
            self::Deduct => 'خصم',
            self::Release => 'إفراج',
            self::Receipt => 'استلام توريد',
        };
    }
}
