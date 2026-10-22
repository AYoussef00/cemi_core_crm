<?php

namespace App\Support\Ui;

/**
 * Maps internal notification category slugs to Arabic labels for display.
 * Unknown or technical slugs are hidden from the UI.
 */
final class NotificationCategory
{
    public static function labelAr(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        return match ($category) {
            'credit_limit' => 'حد الائتمان',
            'settings' => 'الإعدادات',
            default => null,
        };
    }
}
