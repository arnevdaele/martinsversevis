<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Support\CustomerTypeScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('type')->withCount('users')->withMax('orders', 'submitted_at'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Klant')
                    ->description(fn ($record) => $record->contact_name)
                    ->searchable(['name', 'contact_name', 'email', 'vat_number'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('type.name')->label('Type')->badge()->color('gray')->sortable(),
                TextColumn::make('city')->label('Gemeente')->searchable()->sortable()->toggleable(),
                TextColumn::make('users_count')->label('Logins')->alignCenter()->toggleable(),
                TextColumn::make('orders_max_submitted_at')
                    ->label('Laatste bestelling')
                    ->since()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->filters([
                SelectFilter::make('customer_type_id')
                    ->label('Klanttype')
                    ->options(fn () => CustomerTypeScope::options())
                    ->multiple(),
                TernaryFilter::make('is_active')->label('Actief'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nog geen klanten')
            ->emptyStateDescription('Maak een klant aan en nodig daarna de mensen uit die mogen bestellen.');
    }
}
