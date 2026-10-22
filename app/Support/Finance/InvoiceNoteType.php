<?php

namespace App\Support\Finance;

enum InvoiceNoteType: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function labelAr(): string
    {
        return match ($this) {
            self::Credit => 'إشعار دائن',
            self::Debit => 'إشعار مدين',
        };
    }
}
