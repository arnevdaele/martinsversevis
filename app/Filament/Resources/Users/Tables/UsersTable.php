<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'customerTypes']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Naam')->description(fn ($record) => $record->email)->searchable(['name', 'email'])->weight('medium'),
                TextColumn::make('roles.name')->label('Rollen')->badge()->placeholder('Geen rol'),
                TextColumn::make('customerTypes.name')->label('Klanttypes')->badge()->color('gray')->placeholder('Alle'),
                TextColumn::make('last_login_at')->label('Laatst aangemeld')->since()->placeholder('Nooit')->sortable(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->filters([
                SelectFilter::make('roles')->label('Rol')->relationship('roles', 'name'),
                TernaryFilter::make('is_active')->label('Actief'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
