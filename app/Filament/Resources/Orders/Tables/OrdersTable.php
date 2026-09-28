<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\CustomerTypeScope;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer.type', 'customerUser'])->withCount('items'))
            ->defaultSort('submitted_at', 'desc')
            ->poll('30s')
            ->recordUrl(fn ($record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label('Nummer')->searchable()->weight('medium'),
                TextColumn::make('customer.name')
                    ->label('Klant')
                    ->description(fn ($record) => $record->customerUser?->name)
                    ->searchable(),
                TextColumn::make('customer.type.name')->label('Type')->badge()->color('gray')->toggleable(),
                TextColumn::make('submitted_at')->label('Geplaatst')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('requested_delivery_date')->label('Levering')->date('D d/m')->placeholder('—')->sortable(),
                TextColumn::make('items_count')->label('Lijnen')->alignCenter()->toggleable(),
                TextColumn::make('total')->label('Totaal')->money('EUR', locale: 'nl_BE')->alignEnd()->sortable(),
                IconColumn::make('has_unpriced_items')
                    ->label('Dagprijs')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->trueColor('warning')
                    ->falseIcon('')
                    ->tooltip(fn ($state) => $state ? 'Bevat producten zonder prijs' : null)
                    ->toggleable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('customer_type')
                    ->label('Klanttype')
                    ->options(fn () => CustomerTypeScope::options())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $type) => $q->whereHas('customer', fn (Builder $c) => $c->where('customer_type_id', $type)),
                    )),
                Filter::make('delivery')
                    ->label('Leverdatum')
                    ->schema([
                        DatePicker::make('from')->label('Vanaf'),
                        DatePicker::make('until')->label('Tot en met'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('requested_delivery_date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('requested_delivery_date', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        filled($data['from'] ?? null) ? Indicator::make('Levering vanaf '.Carbon::parse($data['from'])->format('d/m/Y'))->removeField('from') : null,
                        filled($data['until'] ?? null) ? Indicator::make('Levering tot en met '.Carbon::parse($data['until'])->format('d/m/Y'))->removeField('until') : null,
                    ]))),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Nog geen bestellingen')
            ->emptyStateDescription('Bestellingen uit het klantenportaal verschijnen hier.');
    }
}
