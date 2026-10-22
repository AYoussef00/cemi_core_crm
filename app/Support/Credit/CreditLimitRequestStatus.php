<?php

namespace App\Support\Credit;

/**
 * Confirmed credit-limit workflow statuses (WORKFLOWS §7).
 * Return-for-revision / resubmission rules are not confirmed — see OPEN_QUESTIONS.
 */
enum CreditLimitRequestStatus: string
{
    case PendingReview = 'pending_review';
    case PendingFinal = 'pending_final';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelAr(): string
    {
        return match ($this) {
            self::PendingReview => 'بانتظار مراجعة الحسابات',
            self::PendingFinal => 'بانتظار الاعتماد النهائي',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::PendingReview || $this === self::PendingFinal;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
