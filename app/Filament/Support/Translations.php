<?php

namespace App\Filament\Support;

use App\Support\Locales;
use Closure;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * A collapsible "Vertalingen" block with one tab per extra language. The
 * callback receives the locale and returns fields named `translations.{locale}.{field}`;
 * {@see field()} builds that name. Empty fields fall back to the Dutch text.
 */
final class Translations
{
    /** @param Closure(string $locale): array $fields */
    public static function section(Closure $fields, ?string $description = null): Section
    {
        $tabs = array_map(
            fn (string $locale) => Tab::make(Locales::label($locale))->schema($fields($locale)),
            Locales::translated(),
        );

        return Section::make('Vertalingen')
            ->description($description ?? 'Wat klanten in een andere taal zien. Leeg laten = de Nederlandse tekst wordt getoond.')
            ->icon('heroicon-o-language')
            ->collapsible()
            ->collapsed(fn (?Model $record) => $record === null)
            ->columnSpanFull()
            ->schema([Tabs::make()->tabs($tabs)]);
    }

    public static function field(string $locale, string $field): string
    {
        return "translations.{$locale}.{$field}";
    }
}
