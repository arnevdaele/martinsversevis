<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Unit: string implements HasLabel
{
    case Kilogram = 'kg';
    case Piece = 'piece';
    case Box = 'box';
    case Portion = 'portion';
    case Litre = 'litre';

    public function getLabel(): string
    {
        return __("units.{$this->value}.label");
    }

    /** Short form next to a price: "€ 24,50 / kg". */
    public function short(): string
    {
        return __("units.{$this->value}.short");
    }

    /** Weighed goods may be ordered in fractions; counted goods only whole. */
    public function allowsDecimals(): bool
    {
        return in_array($this, [self::Kilogram, self::Litre], true);
    }
}
