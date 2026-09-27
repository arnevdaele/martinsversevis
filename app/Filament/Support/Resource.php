<?php

namespace App\Filament\Support;

use Filament\Resources\Resource as FilamentResource;
use Illuminate\Support\Str;

/**
 * Filament title-cases labels ("Sluitingen & Extra Dagen"), which is English
 * style. Dutch (and French) headings take sentence case.
 */
abstract class Resource extends FilamentResource
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
