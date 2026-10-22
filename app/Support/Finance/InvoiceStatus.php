<?php

namespace App\Support\Finance;

enum InvoiceStatus: string
{
    case Issued = 'issued';

    public function labelAr(): string
    {
        return match ($this) {
            self::Issued => 'صادرة',
        };
    }
}
