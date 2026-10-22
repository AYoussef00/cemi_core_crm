<?php

namespace App\Support\SalesRequests;

enum LineAvailability: string
{
    case Available = 'available';
    case NeedsProcurement = 'needs_procurement';

    public function labelAr(): string
    {
        return match ($this) {
            self::Available => 'متوفر (بدون مشتريات)',
            self::NeedsProcurement => 'يحتاج توريد / غير متوفر',
        };
    }
}
