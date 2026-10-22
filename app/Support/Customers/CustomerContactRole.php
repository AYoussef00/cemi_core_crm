<?php

namespace App\Support\Customers;

/**
 * Documented contact roles for a customer record:
 * customer-account contact and accounting contact.
 */
enum CustomerContactRole: string
{
    case Account = 'account';
    case Accounting = 'accounting';

    public function labelAr(): string
    {
        return match ($this) {
            self::Account => 'جهة الاتصال التجارية',
            self::Accounting => 'جهة اتصال المحاسبة',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
