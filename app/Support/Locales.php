<?php

namespace App\Support;

final class Locales
{
    /** @return array<string, string> code => native name */
    public static function all(): array
    {
        return config('locales.available');
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /** The base language: stored in the plain columns, used by staff. */
    public static function default(): string
    {
        return self::codes()[0];
    }

    /** @return list<string> every language except the base one */
    public static function translated(): array
    {
        return array_slice(self::codes(), 1);
    }

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }

    public static function label(string $locale): string
    {
        return self::all()[$locale] ?? strtoupper($locale);
    }
}
