<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Support\RegularCustomers;
use Filament\Widgets\Widget;

/** Regulars who haven't ordered yet this week, though by now they usually have. */
class RegularCustomersMissing extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.regular-customers-missing';

    public static function canView(): bool
    {
        return auth()->user()->can('viewAny', Order::class);
    }

    protected function getViewData(): array
    {
        return ['missing' => RegularCustomers::missing(auth()->user())];
    }
}
