<?php

namespace App\Filament\Resources\DeliverySchedules;

use App\Filament\Resources\DeliverySchedules\Pages\CreateDeliverySchedule;
use App\Filament\Resources\DeliverySchedules\Pages\EditDeliverySchedule;
use App\Filament\Resources\DeliverySchedules\Pages\ListDeliverySchedules;
use App\Filament\Resources\DeliverySchedules\Schemas\DeliveryScheduleForm;
use App\Filament\Support\Resource;
use App\Models\DeliverySchedule;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DeliveryScheduleResource extends Resource
{
    protected static ?string $model = DeliverySchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Levering';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Leverschema\'s';

    protected static ?string $modelLabel = 'leverschema';

    protected static ?string $pluralModelLabel = 'leverschema\'s';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DeliveryScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['customerTypes', 'customers']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Schema')
                    ->description(fn (DeliverySchedule $record) => $record->description)
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('is_default')
                    ->label('')
                    ->badge()
                    ->state(fn (DeliverySchedule $record) => $record->is_default ? 'Standaard' : null)
                    ->color('primary'),
                TextColumn::make('days')
                    ->label('Leverdagen')
                    ->state(fn (DeliverySchedule $record) => DeliveryScheduleForm::summary($record->days()))
                    ->wrap(),
                TextColumn::make('minimum_order_amount')
                    ->label('Minimum')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->placeholder('—'),
                TextColumn::make('usage')
                    ->label('Gebruikt door')
                    ->state(fn (DeliverySchedule $record) => $record->is_default
                        ? 'Alle klanten zonder eigen schema'
                        : (trim(($record->customer_types_count ? "{$record->customer_types_count} klanttype(s) " : '').($record->customers_count ? "{$record->customers_count} klant(en)" : '')) ?: 'Nog niemand'))
                    ->color('gray'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Nog geen leverschema')
            ->emptyStateDescription('Zonder schema kiezen klanten vrij een datum. Maak een standaardschema om leverdagen en besteldeadlines vast te leggen.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliverySchedules::route('/'),
            'create' => CreateDeliverySchedule::route('/create'),
            'edit' => EditDeliverySchedule::route('/{record}/edit'),
        ];
    }
}
