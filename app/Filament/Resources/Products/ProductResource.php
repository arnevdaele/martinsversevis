<?php

namespace App\Filament\Resources\Products;

use App\Enums\Unit;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Support\Resource;
use App\Filament\Support\Translations;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The shared catalogue. Prices do not live here but on each price list, so
 * one product can cost something different per customer type.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogus';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Producten';

    protected static ?string $modelLabel = 'product';

    protected static ?string $pluralModelLabel = 'producten';

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
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                        // Only fill an empty slug: the website may already link to it.
                        if (filled($state) && blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('URL-segment')
                    ->helperText('Voor de toekomstige website.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9\-]+$/')
                    ->maxLength(255),
                Select::make('product_category_id')
                    ->label('Categorie')
                    ->relationship('category', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
                    ->createOptionForm([
                        TextInput::make('name')->label('Naam')->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')->required()->unique('product_categories', 'slug'),
                    ])
                    ->preload()
                    ->native(false),
                TextInput::make('sku')->label('Artikelcode')->unique(ignoreRecord: true)->maxLength(60),
                Select::make('unit')
                    ->label('Eenheid')
                    ->options(Unit::class)
                    ->default(Unit::Kilogram)
                    ->required()
                    ->native(false)
                    ->helperText('Per kg en per liter mag de klant decimalen bestellen, de rest per stuk.'),
                Select::make('vat_rate')
                    ->label('Btw-tarief')
                    // Keys match the decimal:2 cast, or an edit form would show an empty select.
                    ->options(['0.00' => '0%', '6.00' => '6%', '12.00' => '12%', '21.00' => '21%'])
                    ->default('6.00')
                    ->required()
                    ->native(false),
                TextInput::make('origin')->label('Herkomst')->placeholder('Noordzee')->maxLength(255),
                TextInput::make('sort_order')->label('Volgorde')->numeric()->default(0),
                Textarea::make('description')->label('Omschrijving')->rows(3)->columnSpanFull(),
                FileUpload::make('image')
                    ->label('Foto')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('products')
                    ->columnSpanFull(),
                Translations::section(fn (string $locale) => [
                    TextInput::make(Translations::field($locale, 'name'))->label('Naam')->maxLength(255),
                    Textarea::make(Translations::field($locale, 'description'))->label('Omschrijving')->rows(3),
                ]),
                Toggle::make('is_active')
                    ->label('Beschikbaar')
                    ->helperText('Uit = verdwijnt uit alle prijslijsten in het portaal, zonder de prijzen te verliezen.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category')->withCount('priceListItems'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->size(40),
                TextColumn::make('name')
                    ->label('Product')
                    ->description(fn (Product $record) => $record->origin)
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('sku')->label('Code')->color('gray')->searchable()->toggleable(),
                TextColumn::make('category.name')->label('Categorie')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('unit')->label('Eenheid')->badge(),
                TextColumn::make('vat_rate')->label('Btw')->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.').'%')->toggleable(),
                TextColumn::make('price_list_items_count')->label('In prijslijsten')->alignCenter(),
                IconColumn::make('is_active')->label('Beschikbaar')->boolean(),
            ])
            ->filters([
                SelectFilter::make('product_category_id')->label('Categorie')->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('Beschikbaar'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Nog geen producten')
            ->emptyStateDescription('Voeg producten toe en zet ze daarna met een prijs in één of meer prijslijsten.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
