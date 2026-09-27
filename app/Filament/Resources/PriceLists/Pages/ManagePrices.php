<?php

namespace App\Filament\Resources\PriceLists\Pages;

use App\Filament\Resources\PriceLists\PriceListResource;
use App\Filament\Support\Translations;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\CustomerPrices;
use App\Support\ListPrices;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The one screen for a list's prices: every product, each with a price
 * field. Typing a price puts the product in the list, emptying it takes it
 * out. No separate "add products" step.
 *
 * @property PriceList $record
 */
class ManagePrices extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = PriceListResource::class;

    protected static ?string $navigationLabel = 'Prijzen';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    /** For a list made for one customer: the prices they would pay without it. */
    protected ?Collection $referencePrices = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('view', $this->record), 403);
    }

    public function getBreadcrumb(): string
    {
        return 'Prijzen';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $who = collect([
            $this->record->customerTypes->pluck('name')->implode(', '),
            $this->record->customers->pluck('name')->implode(', '),
        ])->filter()->implode(' · ');

        return ($who ? "Voor: {$who}" : 'Nog aan niemand gekoppeld — kies onder Instellingen wie deze lijst ziet.')
            .($this->record->is_active ? '' : ' · Inactief');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make($this->forOneCustomer()
                ? 'Vul enkel de producten in waarvoor deze klant een andere prijs krijgt. Voor al de rest betaalt de klant de prijs van zijn klanttype (grijze kolom).'
                : 'Vul een prijs in om een product in deze lijst te zetten. Leeg = niet in de lijst. Vink "Dagprijs" aan als de prijs pas bij levering vastligt. Wijzigingen worden meteen bewaard. Prijzen excl. btw.')
                ->color('gray'),
            EmbeddedTable::make(),
        ]);
    }

    public static function canEdit(PriceList $list): bool
    {
        return auth()->user()->can('update', $list);
    }

    private function item(Product $product): ?PriceListItem
    {
        return $product->priceListItems->first();
    }

    /** A list linked to exactly one customer and no customer types is that customer's exceptions. */
    private function forOneCustomer(): ?Customer
    {
        return $this->record->customerTypes->isEmpty() && $this->record->customers->count() === 1
            ? $this->record->customers->first()
            : null;
    }

    private function reloadItem(Product $product): void
    {
        $product->load(['priceListItems' => fn ($query) => $query->where('price_list_id', $this->record->id)]);
    }

    public function table(Table $table): Table
    {
        $list = $this->record;
        $editable = static::canEdit($list);
        $customer = $this->forOneCustomer();

        return $table
            ->query(Product::query()
                ->with(['category', 'priceListItems' => fn ($query) => $query->where('price_list_id', $list->id)]))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->select('products.*')
                ->orderBy('product_categories.sort_order')
                ->orderBy('products.sort_order')
                ->orderBy('products.name'))
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->description(fn (Product $record) => collect([$record->category?->name, 'per '.$record->unit->short()])->filter()->implode(' · '))
                    ->searchable(['products.name', 'products.sku'])
                    ->weight(fn (Product $record) => $this->item($record) ? 'semibold' : null)
                    ->color(fn (Product $record) => $this->item($record) ? null : 'gray'),

                TextColumn::make('reference')
                    ->label('Via klanttype')
                    ->visible($customer !== null)
                    ->state(function (Product $record) use ($customer, $list) {
                        $this->referencePrices ??= CustomerPrices::for($customer, withoutList: $list->id);
                        $item = $this->referencePrices->get($record->id);

                        return $item ? ($item->price === null ? 'dagprijs' : Money::format($item->price)) : null;
                    })
                    ->placeholder('—')
                    ->color('gray')
                    ->alignEnd(),

                TextInputColumn::make('price')
                    ->label('Prijs (€)')
                    ->state(fn (Product $record) => ($item = $this->item($record)) && $item->price !== null
                        ? number_format((float) $item->price, 2, ',', '')
                        : null)
                    ->placeholder(fn (Product $record) => $this->item($record) ? 'dagprijs' : ($customer ? 'zoals klanttype' : 'niet in lijst'))
                    ->disabled(fn (Product $record) => ! $editable || ($this->item($record) && $this->item($record)->price === null))
                    ->rules(['nullable', 'regex:/^\s*\d{1,6}([.,]\d{1,2})?\s*$/'])
                    ->validationMessages(['regex' => 'Typ een bedrag, bv. 24,50.'])
                    ->updateStateUsing(function (Product $record, ?string $state) use ($list) {
                        ListPrices::set($record, $list, ListPrices::parse($state), dayPrice: false);
                        $this->reloadItem($record);

                        return $state;
                    })
                    ->extraInputAttributes(['inputmode' => 'decimal', 'style' => 'text-align: right'])
                    ->width('9rem'),

                CheckboxColumn::make('day_price')
                    ->label('Dagprijs')
                    ->state(fn (Product $record) => ($item = $this->item($record)) !== null && $item->price === null)
                    ->disabled(! $editable)
                    ->updateStateUsing(function (Product $record, $state) use ($list) {
                        ListPrices::set($record, $list, null, dayPrice: (bool) $state);
                        $this->reloadItem($record);

                        return $state;
                    })
                    ->alignCenter(),

                TextInputColumn::make('min_quantity')
                    ->label('Minimum')
                    ->state(fn (Product $record) => ($min = $this->item($record)?->min_quantity) === null
                        ? null
                        : rtrim(rtrim(number_format((float) $min, 3, ',', ''), '0'), ','))
                    ->placeholder(fn (Product $record) => $this->item($record) ? '—' : '')
                    ->disabled(fn (Product $record) => ! $editable || ! $this->item($record))
                    ->rules(['nullable', 'regex:/^\s*\d{1,6}([.,]\d{1,3})?\s*$/'])
                    ->updateStateUsing(function (Product $record, ?string $state) {
                        $this->item($record)?->update(['min_quantity' => filled($state) ? (float) str_replace(',', '.', $state) : null]);

                        return $state;
                    })
                    ->extraInputAttributes(['inputmode' => 'decimal', 'style' => 'text-align: right'])
                    ->width('7rem')
                    ->toggleable(),

                TextColumn::make('note')
                    ->label('Opmerking in portaal')
                    ->state(fn (Product $record) => $this->item($record)?->note)
                    ->placeholder('—')
                    ->color('gray')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('note')
                    ->label('Opmerking')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->iconButton()
                    ->tooltip('Opmerking voor de klant, bv. "enkel op vrijdag"')
                    ->visible(fn (Product $record) => $editable && $this->item($record))
                    ->fillForm(fn (Product $record) => $this->item($record)->only(['note', 'translations']))
                    ->schema([
                        TextInput::make('note')->label('Opmerking in het portaal')->maxLength(255),
                        Translations::section(fn (string $locale) => [
                            TextInput::make(Translations::field($locale, 'note'))->label('Opmerking')->maxLength(255),
                        ]),
                    ])
                    ->action(function (Product $record, array $data) {
                        $this->item($record)->update($data);
                        $this->reloadItem($record);
                    }),
            ])
            ->filters([
                SelectFilter::make('in_list')
                    ->label('Tonen')
                    ->placeholder('Alle producten')
                    ->options(['in' => 'Enkel in deze lijst', 'out' => 'Nog niet in deze lijst'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'in' => $query->whereHas('priceListItems', fn (Builder $items) => $items->where('price_list_id', $list->id)),
                        'out' => $query->whereDoesntHave('priceListItems', fn (Builder $items) => $items->where('price_list_id', $list->id)),
                        default => $query,
                    }),
                SelectFilter::make('product_category_id')->label('Categorie')->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('Beschikbare producten')->attribute('products.is_active')->default(true),
            ])
            ->emptyStateHeading('Nog geen producten')
            ->emptyStateDescription('Maak eerst producten aan onder Catalogus → Producten.');
    }

    protected function getHeaderActions(): array
    {
        $list = $this->record;
        $otherLists = fn () => PriceList::whereKeyNot($list->id)->orderBy('name')->pluck('name', 'id');

        return [
            Action::make('copyFrom')
                ->label('Prijzen overnemen')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn () => static::canEdit($list) && $otherLists()->isNotEmpty())
                ->modalHeading('Prijzen overnemen van een andere lijst')
                ->modalDescription('Zet de producten van die lijst in deze lijst, eventueel duurder of goedkoper. Dagprijzen blijven dagprijzen.')
                ->schema([
                    Select::make('from')->label('Van lijst')->options($otherLists)->required()->native(false),
                    Grid::make(2)->schema([
                        TextInput::make('percentage')->label('Aanpassing')->helperText('15 = 15% duurder, -5 = 5% goedkoper.')->numeric()->default(0)->suffix('%')->required(),
                        Select::make('step')->label('Afronden')->options(ListPrices::steps())->default('0.10')->required()->native(false),
                    ]),
                    Radio::make('overwrite')
                        ->label('Producten die hier al een prijs hebben')
                        ->options(['0' => 'Laten staan', '1' => 'Overschrijven'])
                        ->default('0')
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data) use ($list) {
                    $count = ListPrices::copy(PriceList::findOrFail($data['from']), $list, (float) $data['percentage'], (float) $data['step'], (bool) $data['overwrite']);
                    Notification::make()->success()->title("{$count} prijs/prijzen overgenomen")->send();
                    $this->resetTable();
                }),

            Action::make('adjust')
                ->label('Alle prijzen aanpassen')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('gray')
                ->visible(fn () => static::canEdit($list))
                ->modalHeading('Alle prijzen in deze lijst aanpassen')
                ->modalDescription('Bv. +3% voor alles. Dagprijzen blijven ongemoeid.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('percentage')->label('Aanpassing')->numeric()->required()->suffix('%'),
                        Select::make('step')->label('Afronden')->options(ListPrices::steps())->default('0.10')->required()->native(false),
                    ]),
                ])
                ->action(function (array $data) use ($list) {
                    $count = ListPrices::adjust($list, (float) $data['percentage'], (float) $data['step']);
                    Notification::make()->success()->title("{$count} prijs/prijzen aangepast")->send();
                    $this->resetTable();
                }),
        ];
    }
}
