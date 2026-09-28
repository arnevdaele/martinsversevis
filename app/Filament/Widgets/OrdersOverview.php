<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Pages\PickingList;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/** The day at a glance: what goes out today and tomorrow, and what still needs a hand. */
class OrdersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()->can('viewAny', Order::class);
    }

    protected function getStats(): array
    {
        $today = CarbonImmutable::today();

        return [
            $this->day('Levering vandaag', $today),
            $this->day('Levering morgen', $today->addDay()),
            Stat::make('Te bevestigen', $this->orders()->where('status', OrderStatus::New)->count())
                ->description('Nieuwe bestellingen uit het portaal'),
            Stat::make('Dagprijzen in te vullen', $this->open()->where('has_unpriced_items', true)->count())
                ->description('Open bestellingen met lijnen zonder prijs'),
        ];
    }

    private function day(string $label, CarbonImmutable $date): Stat
    {
        $orders = $this->open()->whereDate('requested_delivery_date', $date);
        $unconfirmed = (clone $orders)->where('status', OrderStatus::New)->count();
        $day = $date->translatedFormat('l j F');

        return Stat::make($label, $orders->count())
            ->description($unconfirmed ? "{$day} · {$unconfirmed} nog niet bevestigd" : $day)
            ->descriptionColor($unconfirmed ? 'warning' : 'gray')
            ->url(PickingList::getUrl(['date' => $date->toDateString()]));
    }

    /** Still to be delivered: new or confirmed. */
    private function open(): Builder
    {
        return $this->orders()->whereIn('status', [OrderStatus::New, OrderStatus::Confirmed]);
    }

    private function orders(): Builder
    {
        return Order::query()->visibleTo(auth()->user());
    }
}
