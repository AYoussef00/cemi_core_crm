<?php

namespace App\Support\Sales;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingApproval => 'بانتظار الاعتماد',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
            self::Cancelled => 'ملغى',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Counts toward PO allocated qty (blocks unallocated). */
    public function allocatesPoQuantity(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved], true);
    }
}
