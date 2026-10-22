<?php

namespace App\Support\Procurement;

enum ProcurementTaskStatus: string
{
    case Open = 'open';
    case CostRecorded = 'cost_recorded';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::CostRecorded => 'تكلفة مسجّلة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
