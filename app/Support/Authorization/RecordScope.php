<?php

namespace App\Support\Authorization;

/**
 * Record-level scope vocabulary from docs/ROLES_AND_PERMISSIONS.md §2.
 */
enum RecordScope: string
{
    case Own = 'own';
    case Assigned = 'assigned';
    case Cust = 'cust';
    case All = 'all';
    case Work = 'work';
    case Referred = 'referred';
    case Admin = 'admin';
    case Cost = 'cost';

    public function labelAr(): string
    {
        return match ($this) {
            self::Own => 'الخاص بالمستخدم',
            self::Assigned => 'العملاء المُعيَّنون',
            self::Cust => 'سجلات العملاء المُعيَّنين',
            self::All => 'جميع السجلات',
            self::Work => 'مرتبط بالعمل التشغيلي',
            self::Referred => 'المُحال للمشتريات',
            self::Admin => 'وصول إداري (دعم)',
            self::Cost => 'تكلفة الشراء فقط',
        };
    }
}
