<?php

namespace App\Support;

use App\Enums\Unit;

/**
 * Money and quantities are formatted on the server, in Belgian notation for
 * the current language ("€ 1.234,50" in Dutch, "1 234,50 €" in French), so
 * what a customer sees never depends on their browser's locale.
 */
final class Money
{
    private const NARROW_NBSP = "\u{202F}";

    public static function format(float|string|null $amount, ?string $locale = null): string
    {
        if ($amount === null) {
            return '—';
        }

        $locale ??= app()->getLocale();

        return $locale === 'fr'
            ? number_format((float) $amount, 2, ',', self::NARROW_NBSP)."\u{00A0}€"
            : '€ '.number_format((float) $amount, 2, ',', '.');
    }

    public static function quantity(float|string $quantity, Unit $unit, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $thousands = $locale === 'fr' ? self::NARROW_NBSP : '.';
        $value = rtrim(rtrim(number_format((float) $quantity, 3, ',', $thousands), '0'), ',');

        return "{$value} {$unit->short()}";
    }
}
