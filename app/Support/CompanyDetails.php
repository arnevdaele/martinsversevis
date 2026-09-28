<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Who we are, for the header of delivery notes. Set by staff under
 * Beheer › Bedrijfsgegevens; until then the app name and sending address.
 */
class CompanyDetails
{
    private const KEY = 'company';

    /** @return array{name: string, address: list<string>, vat_number: ?string, phone: ?string, email: ?string} */
    public static function get(): array
    {
        $saved = Setting::read(self::KEY);

        return [
            'name' => filled($saved['name'] ?? null) ? $saved['name'] : config('app.name'),
            'address' => array_values(array_filter($saved['address'] ?? [])),
            'vat_number' => $saved['vat_number'] ?? null,
            'phone' => $saved['phone'] ?? null,
            'email' => array_key_exists('email', $saved) ? $saved['email'] : config('mail.from.address'),
        ];
    }

    /** @param  array{name?: ?string, address?: string|list<string>|null, vat_number?: ?string, phone?: ?string, email?: ?string}  $details  the address as lines or as text */
    public static function save(array $details): void
    {
        $address = $details['address'] ?? [];
        $lines = is_array($address) ? $address : preg_split('/\R/', $address);

        Setting::write(self::KEY, [
            'name' => trim((string) ($details['name'] ?? '')),
            'address' => array_values(array_filter(array_map('trim', $lines), 'strlen')),
            'vat_number' => self::blankToNull($details['vat_number'] ?? null),
            'phone' => self::blankToNull($details['phone'] ?? null),
            'email' => self::blankToNull($details['email'] ?? null),
        ]);
    }

    private static function blankToNull(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }
}
