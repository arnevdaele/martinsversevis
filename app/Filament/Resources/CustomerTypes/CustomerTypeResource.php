<?php

namespace App\Filament\Resources\CustomerTypes;

use App\Filament\Resources\CustomerTypes\Pages\ManageCustomerTypes;
use App\Filament\Support\Resource;
use App\Models\CustomerType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class CustomerTypeResource extends Resource
{
    protected static ?string $model = CustomerType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Klanten';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Klanttypes';

    protected static ?string $modelLabel = 'klanttype';

    protected static ?string $pluralModelLabel = 'klanttypes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?CustomerType $record) {
                        if (filled($state) && blank($get('slug')) && ! $record?->is_system) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9\-]+$/')
                    // The code wires system types into the defaults; renaming is fine, recoding is not.
                    ->disabled(fn (?CustomerType $record) => $record?->is_system)
                    ->dehydrated(fn (?CustomerType $record) => ! $record?->is_system),
                Textarea::make('description')->label('Omschrijving')->rows(2)->columnSpanFull(),
                Select::make('priceLists')
                    ->label('Prijslijsten')
                    ->relationship('priceLists', 'name')
                    ->multiple()
                    ->preload()
                    ->helperText('Klanten van dit type zien deze lijsten in het portaal.')
                    ->columnSpanFull(),
                Select::make('delivery_schedule_id')
                    ->label('Leverschema')
                    ->relationship('deliverySchedule', 'name')
                    ->placeholder('Standaardschema')
                    ->helperText('Leeg = het standaardschema. Een klant kan nog een eigen schema krijgen.')
                    ->columnSpanFull(),
                TagsInput::make('notification_emails')
                    ->label('Extra e-mailadressen voor bestellingen')
                    ->helperText('Krijgen elke bestelling van dit klanttype, bovenop de beheerders met het recht "E-mail ontvangen bij nieuwe bestellingen". Druk op Enter na elk adres.')
                    ->placeholder('naam@martinsversevis.be')
                    ->nestedRecursiveRules(['email'])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('customers')->with(['priceLists', 'deliverySchedule']))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Type')->description(fn (CustomerType $record) => $record->description)->weight('medium'),
                TextColumn::make('priceLists.name')->label('Prijslijsten')->badge()->color('gray')->placeholder('Geen'),
                TextColumn::make('deliverySchedule.name')->label('Leverschema')->placeholder('Standaard')->color('gray')->toggleable(),
                TextColumn::make('customers_count')->label('Klanten')->alignCenter(),
                TextColumn::make('notification_emails')->label('Extra ontvangers')->badge()->color('gray')->placeholder('—')->toggleable(),
                IconColumn::make('is_system')
                    ->label('Standaard')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('')
                    ->tooltip(fn ($state) => $state ? 'Standaardtype: kan hernoemd maar niet verwijderd worden' : null),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomerTypes::route('/')];
    }
}
