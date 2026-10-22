<?php

namespace App\Support\Documents;

/**
 * Human-facing document numbers (NUM*): PREFIX-######### starting at 100.
 *
 * Internal primary keys stay numeric for routes/FKs; display uses this format.
 */
final class DocumentNumber
{
    public const SEQUENCE_START = 100;

    public const PAD_LENGTH = 9;

    public const PREFIX_QUOTATION = 'QA';

    public const PREFIX_PURCHASE_ORDER = 'PO';

    public const PREFIX_INVOICE = 'INV';

    public const PREFIX_DELIVERY_NOTE = 'DN';

    public static function format(string $prefix, int $id): string
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Document id must be a positive integer.');
        }

        $sequence = $id + (self::SEQUENCE_START - 1);

        return $prefix.'-'.str_pad((string) $sequence, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function quotation(int $id): string
    {
        return self::format(self::PREFIX_QUOTATION, $id);
    }

    public static function purchaseOrder(int $id): string
    {
        return self::format(self::PREFIX_PURCHASE_ORDER, $id);
    }

    public static function invoice(int $id): string
    {
        return self::format(self::PREFIX_INVOICE, $id);
    }

    public static function deliveryNote(int $id): string
    {
        return self::format(self::PREFIX_DELIVERY_NOTE, $id);
    }
}
