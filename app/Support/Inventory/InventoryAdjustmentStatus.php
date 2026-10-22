<?php

namespace App\Support\Inventory;

enum InventoryAdjustmentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الاعتماد',
            self::Approved => 'معتمدة',
            self::Rejected => 'مرفوضة',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }
}
