<?php

namespace App\Support\Finance;

enum CollectionStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Reversed = 'reversed';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Confirmed => 'مؤكد',
            self::Reversed => 'معكوس',
        };
    }

    public function reducesUnpaid(): bool
    {
        return $this === self::Confirmed;
    }
}
