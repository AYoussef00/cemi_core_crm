<?php

namespace App\Support\Quotations;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case PendingPriceApproval = 'pending_price_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
    case Expired = 'expired';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingPriceApproval => 'بانتظار اعتماد السعر',
            self::Approved => 'معتمد',
            self::Sent => 'مُرسل',
            self::Cancelled => 'ملغى',
            self::Superseded => 'مُستبدل',
            self::Expired => 'منتهي الصلاحية',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Superseded, self::Expired], true);
    }

    public function canSend(): bool
    {
        return $this === self::Approved;
    }

    /** Customer-facing print/PDF after Sales Manager approval. */
    public function canOfferPdf(): bool
    {
        return in_array($this, [self::Approved, self::Sent], true);
    }
}
