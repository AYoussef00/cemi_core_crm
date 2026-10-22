<?php

namespace App\Support\Visits;

/**
 * Minimal visit lifecycle (implementation assumption — see OPEN_QUESTIONS VISIT-STATUS).
 * Confirmed concepts: scheduled interaction, completed with outcome/notes, cancel allowed by matrix.
 */
enum VisitStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدولة',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Scheduled;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
