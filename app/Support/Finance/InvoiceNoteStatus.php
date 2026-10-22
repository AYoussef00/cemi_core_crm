<?php

namespace App\Support\Finance;

enum InvoiceNoteStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingApproval => 'بانتظار الاعتماد',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
        };
    }

    public function affectsUnpaid(): bool
    {
        return $this === self::Approved;
    }
}
