<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\OrderExport;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::New, OrderStatus::Confirmed])),
            'new' => Tab::make('Nieuw')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::New)),
            'all' => Tab::make('Alle'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $lastMonth = CarbonImmutable::today()->subMonthNoOverflow();

        return [
            Action::make('export')
                ->label('Exporteren')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => auth()->user()->can('export', Order::class))
                ->modalHeading('Bestellingen exporteren')
                ->modalDescription('Een CSV-bestand voor de boekhouding, met de gewogen hoeveelheden en de btw per tarief. Opent in Excel.')
                ->modalSubmitActionLabel('Downloaden')
                ->schema([
                    DatePicker::make('from')
                        ->label('Leverdatum vanaf')
                        ->default($lastMonth->startOfMonth())
                        ->required(),
                    DatePicker::make('until')
                        ->label('Leverdatum tot en met')
                        ->default($lastMonth->endOfMonth())
                        ->afterOrEqual('from')
                        ->required(),
                    CheckboxList::make('statuses')
                        ->label('Status')
                        ->options([
                            OrderStatus::Delivered->value => OrderStatus::Delivered->getLabel(),
                            OrderStatus::Confirmed->value => OrderStatus::Confirmed->getLabel(),
                            OrderStatus::New->value => OrderStatus::New->getLabel(),
                        ])
                        ->default([OrderStatus::Delivered->value])
                        ->helperText('Geannuleerde bestellingen gaan nooit mee. Zonder leverdatum telt de besteldatum.')
                        ->columns(3)
                        ->required(),
                    Radio::make('layout')
                        ->label('Eén regel per')
                        ->options([
                            OrderExport::PER_ORDER => 'Bestelling (met maatstaf en btw per tarief)',
                            OrderExport::PER_LINE => 'Productlijn',
                        ])
                        ->default(OrderExport::PER_ORDER)
                        ->required(),
                ])
                ->action(function (array $data): ?StreamedResponse {
                    $from = CarbonImmutable::parse($data['from'])->startOfDay();
                    $until = CarbonImmutable::parse($data['until'])->startOfDay();
                    $orders = OrderExport::orders(auth()->user(), $from, $until, array_map(OrderStatus::from(...), $data['statuses']));

                    if ($orders->isEmpty()) {
                        Notification::make()->warning()->title('Geen bestellingen in die periode.')->send();

                        return null;
                    }

                    $csv = OrderExport::csv(OrderExport::rows($orders, $data['layout']));
                    $name = sprintf('bestellingen-%s-%s-%s.csv', $data['layout'] === OrderExport::PER_LINE ? 'lijnen' : 'totalen', $from->toDateString(), $until->toDateString());

                    return response()->streamDownload(fn () => print ($csv), $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
