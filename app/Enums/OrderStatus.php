<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("orders.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Confirmed => 'warning',
            self::Delivered => 'success',
            self::Cancelled => 'gray',
        };
    }
}
