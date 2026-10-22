<?php

use App\Support\Documents\DocumentNumber;
use App\Support\Money\Money;

if (! function_exists('money')) {
    /**
     * Format a money amount for display (thousands separators, no trailing .00).
     */
    function money(float|string|null $amount): string
    {
        return Money::display($amount);
    }
}

if (! function_exists('qty')) {
    /**
     * Format a quantity for display (thousands separators, trailing zeros stripped).
     */
    function qty(float|string|null $quantity): string
    {
        return Money::displayQuantity($quantity);
    }
}

if (! function_exists('doc_no')) {
    /**
     * Format a document reference number (e.g. QA-000000100).
     */
    function doc_no(string $prefix, int|string|null $id): string
    {
        if ($id === null || $id === '' || (int) $id < 1) {
            return '—';
        }

        return DocumentNumber::format($prefix, (int) $id);
    }
}
