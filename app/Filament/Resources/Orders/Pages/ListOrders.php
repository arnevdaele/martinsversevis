<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

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
}
