<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Bestellingen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', Order::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Nummer')->weight('medium')->searchable(),
                TextColumn::make('submitted_at')->label('Geplaatst')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('requested_delivery_date')->label('Levering')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('total')->label('Totaal')->money('EUR', locale: 'nl_BE')->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
