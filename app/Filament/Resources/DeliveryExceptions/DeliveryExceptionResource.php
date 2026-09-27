<?php

namespace App\Filament\Resources\DeliveryExceptions;

use App\Filament\Resources\DeliveryExceptions\Pages\ManageDeliveryExceptions;
use App\Filament\Support\Resource;
use App\Filament\Support\Translations;
use App\Models\DeliveryException;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Holidays and one-off extra delivery days, on top of every schedule. */
class DeliveryExceptionResource extends Resource
{
    protected static ?string $model = DeliveryException::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Levering';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Sluitingen & extra dagen';

    protected static ?string $modelLabel = 'uitzondering';

    protected static ?string $pluralModelLabel = 'sluitingen & extra dagen';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ToggleButtons::make('kind')
                    ->label('Wat wil je instellen?')
                    ->options([
                        DeliveryException::CLOSED => 'Gesloten — geen leveringen',
                        DeliveryException::EXTRA => 'Extra leverdag',
                    ])
                    ->icons([
                        DeliveryException::CLOSED => 'heroicon-o-no-symbol',
                        DeliveryException::EXTRA => 'heroicon-o-plus-circle',
                    ])
                    ->colors([DeliveryException::CLOSED => 'danger', DeliveryException::EXTRA => 'success'])
                    ->default(DeliveryException::CLOSED)
                    ->inline()
                    ->required()
                    ->live(),

                Grid::make(2)
                    ->visible(fn (Get $get) => $get('kind') === DeliveryException::CLOSED)
                    ->schema([
                        DatePicker::make('starts_on')
                            ->label('Gesloten vanaf')
                            ->native(false)
                            ->displayFormat('D d/m/Y')
                            ->required()
                            ->live(),
                        DatePicker::make('ends_on')
                            ->label('Tot en met')
                            ->helperText('Leeg = enkel die ene dag.')
                            ->native(false)
                            ->displayFormat('D d/m/Y')
                            ->afterOrEqual('starts_on'),
                    ]),

                Grid::make(2)
                    ->visible(fn (Get $get) => $get('kind') === DeliveryException::EXTRA)
                    ->schema([
                        DatePicker::make('starts_on')
                            ->label('Leverdag')
                            ->native(false)
                            ->displayFormat('D d/m/Y')
                            ->required(),
                        DateTimePicker::make('cutoff_at')
                            ->label('Bestellen tot')
                            ->helperText('Leeg = de dag ervoor om 16:00.')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('D d/m/Y H:i'),
                    ]),

                TextInput::make('reason')
                    ->label('Reden (zichtbaar voor klanten)')
                    ->placeholder('bv. Kerstvakantie, Extra levering voor oudejaar')
                    ->maxLength(255),

                Translations::section(fn (string $locale) => [
                    TextInput::make(Translations::field($locale, 'reason'))->label('Reden')->maxLength(255),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('kind')
                    ->label('Soort')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === DeliveryException::CLOSED ? 'Gesloten' : 'Extra leverdag')
                    ->color(fn (string $state) => $state === DeliveryException::CLOSED ? 'danger' : 'success'),
                TextColumn::make('period')
                    ->label('Wanneer')
                    ->state(fn (DeliveryException $record) => $record->starts_on->isSameDay($record->ends_on)
                        ? $record->starts_on->locale('nl')->translatedFormat('D j M Y')
                        : $record->starts_on->locale('nl')->translatedFormat('D j M').' – '.$record->ends_on->locale('nl')->translatedFormat('D j M Y'))
                    ->weight('medium'),
                TextColumn::make('cutoff_at')
                    ->label('Bestellen tot')
                    ->dateTime('D d/m H:i')
                    ->placeholder(fn (DeliveryException $record) => $record->isClosure() ? '—' : 'dag ervoor 16:00'),
                TextColumn::make('reason')->label('Reden')->placeholder('—')->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('Periode')
                    ->placeholder('Alles')
                    ->trueLabel('Komend')
                    ->falseLabel('Voorbij')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereDate('ends_on', '>=', today()),
                        false: fn (Builder $query) => $query->whereDate('ends_on', '<', today()),
                    ),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Geen sluitingen of extra dagen gepland')
            ->emptyStateDescription('Voeg vakantieperiodes toe, of een extra leverdag rond de feestdagen. Ze gelden voor alle leverschema\'s.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryExceptions::route('/')];
    }
}
