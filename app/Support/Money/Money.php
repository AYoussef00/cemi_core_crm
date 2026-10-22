<?php

namespace App\Support\Money;

/**
 * Phase 4 money rules: PM1, PM4a (no discounts), PM4b (2 dp half-up), TX8 (15% VAT), TX2 (VAT-inclusive).
 */
final class Money
{
    public const VAT_RATE = 0.15;

    public const CURRENCY = 'SAR';

    public static function round2(float|string $amount): string
    {
        return number_format(round((float) $amount, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }

    /**
     * UI/PDF display: thousands separators, drop trailing .00 (e.g. 2600000.00 → 2,600,000).
     * Meaningful fractions stay (e.g. 175.25 → 175.25). Storage/calc still use round2.
     */
    public static function display(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $rounded = round((float) $amount, 2, PHP_ROUND_HALF_UP);

        if (abs($rounded - round($rounded)) < 0.0000001) {
            return number_format((int) round($rounded), 0, '.', ',');
        }

        return number_format($rounded, 2, '.', ',');
    }

    /**
     * Quantity display: thousands separators, up to 3 dp, trailing zeros stripped (2000.000 → 2,000).
     */
    public static function displayQuantity(float|string|null $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            return '—';
        }

        $rounded = round((float) $quantity, 3, PHP_ROUND_HALF_UP);

        if (abs($rounded - round($rounded)) < 0.0000001) {
            return number_format((int) round($rounded), 0, '.', ',');
        }

        $formatted = number_format($rounded, 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    /**
     * PM1 + TX2: selling_price (VAT-inclusive) = supplier_cost + margin_amount.
     */
    public static function sellingPriceVatInclusive(float|string $supplierCost, float|string $marginAmount): string
    {
        return self::round2((float) $supplierCost + (float) $marginAmount);
    }

    /**
     * Implied net (ex-VAT) from a VAT-inclusive price at fixed 15%.
     */
    public static function netFromInclusive(float|string $inclusive): string
    {
        return self::round2((float) $inclusive / (1 + self::VAT_RATE));
    }

    /**
     * Implied VAT portion of a VAT-inclusive price at fixed 15%.
     */
    public static function vatFromInclusive(float|string $inclusive): string
    {
        $inclusive = (float) $inclusive;

        return self::round2($inclusive - (float) self::netFromInclusive($inclusive));
    }
}
