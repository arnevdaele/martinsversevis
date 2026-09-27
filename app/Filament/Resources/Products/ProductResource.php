<?php

namespace App\Filament\Resources\Products;

use App\Enums\Unit;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Support\Resource;
use App\Filament\Support\Translations;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\ListPrices;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
                self::pricesSection(),
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

    /**
     * The general lists (not the ones made for a single customer), each with
     * a price and a day-price box, so a new product is priced everywhere in
     * the same step it is created.
     *
     * @return Collection<int, PriceList>
     */
    public static function generalLists()
    {
        return PriceList::query()
            ->where(fn (Builder $query) => $query->whereHas('customerTypes')->orWhereDoesntHave('customers'))
            ->with('customerTypes')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public static function pricesSection(): Section
    {
        $rows = self::generalLists()->map(fn (PriceList $list) => Grid::make(3)->schema([
            TextInput::make("prices.{$list->id}.price")
                ->label($list->name.($list->is_active ? '' : ' (inactief)'))
                ->helperText($list->customerTypes->isEmpty() ? 'Aan geen klanttype gekoppeld' : 'Voor: '.$list->customerTypes->pluck('name')->implode(', '))
                ->placeholder('niet in deze lijst')
                ->prefix('€')
                ->inputMode('decimal')
                ->rule('regex:/^\s*\d{1,6}([.,]\d{1,2})?\s*$/')
                ->validationMessages(['regex' => 'Typ een bedrag, bv. 24,50.'])
                ->disabled(fn (Get $get) => (bool) $get("prices.{$list->id}.day"))
                ->columnSpan(2),
            Checkbox::make("prices.{$list->id}.day")
                ->label('Dagprijs')
                ->live()
                ->extraFieldWrapperAttributes(['style' => 'margin-top: 2rem']),
        ]))->all();

        return Section::make('Prijzen')
            ->description('Excl. btw. Leeg = niet in die lijst. Vink "Dagprijs" aan als de prijs pas bij levering vastligt.')
            ->icon('heroicon-o-currency-euro')
            ->columnSpanFull()
            ->visible(fn () => auth()->user()->can('price-lists.update') && $rows !== [])
            ->schema($rows);
    }

    /** @return array<int, array{price: ?string, day: bool}> */
    public static function pricesFormState(Product $product): array
    {
        return $product->priceListItems()->get()
            ->mapWithKeys(fn (PriceListItem $item) => [$item->price_list_id => [
                'price' => $item->price === null ? null : number_format((float) $item->price, 2, ',', ''),
                'day' => $item->price === null,
            ]])
            ->all();
    }

    /** Save the product and, if the user may, its prices in the general lists. */
    public static function saveWithPrices(Product $product, array $data): Product
    {
        $prices = $data['prices'] ?? null;
        unset($data['prices']);

        DB::transaction(function () use ($product, $data, $prices) {
            $product->fill($data)->save();

            if (is_array($prices) && auth()->user()->can('price-lists.update')) {
                foreach (self::generalLists() as $list) {
                    $row = $prices[$list->id] ?? [];
                    ListPrices::set($product, $list, ListPrices::parse($row['price'] ?? null), (bool) ($row['day'] ?? false));
                }
            }
        });

        return $product;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'priceListItems.priceList']))
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
                TextColumn::make('prices')
                    ->label('Prijzen')
                    ->state(fn (Product $record) => $record->priceListItems
                        ->sortBy(fn (PriceListItem $item) => $item->priceList->name)
                        ->map(fn (PriceListItem $item) => $item->priceList->name.' '.($item->price === null ? 'dagprijs' : Money::format($item->price)))
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('In geen enkele lijst')
                    ->color('gray')
                    ->size('sm'),
                IconColumn::make('is_active')->label('Beschikbaar')->boolean(),
            ])
            ->filters([
                SelectFilter::make('product_category_id')->label('Categorie')->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('Beschikbaar'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Product $record) => $data + ['prices' => self::pricesFormState($record)])
                    ->using(fn (Product $record, array $data) => self::saveWithPrices($record, $data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Nog geen producten')
            ->emptyStateDescription('Voeg producten toe en zet ze daarna met een prijs in één of meer prijslijsten.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
