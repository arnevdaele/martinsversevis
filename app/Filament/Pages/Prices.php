<?php

namespace App\Filament\Pages;

use App\Models\PriceList;
use App\Models\Product;
use App\Support\PriceGrid;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Every product against every price list, in one grid. A product is added
 * to a list by typing its price, and taken out by clearing the cell — no
 * more opening each list and adding the same product again.
 */
class Prices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogus';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Prijzen';

    protected static ?string $title = 'Prijzen';

    /** @var Collection<int, PriceList>|null */
    protected ?Collection $lists = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('price-lists.view') ?? false;
    }

    public static function canEdit(): bool
    {
        return auth()->user()?->can('price-lists.update') ?? false;
    }

    /** @return Collection<int, PriceList> */
    protected function lists(): Collection
    {
        return $this->lists ??= PriceList::query()->with('customerTypes')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Typ een prijs in een cel om het product in die lijst te zetten; maak de cel leeg om het eruit te halen. Typ "d" voor dagprijs. Elke cel wordt meteen bewaard. Prijzen zijn excl. btw.')
                ->color('gray'),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $editable = static::canEdit();

        $listColumns = $this->lists()->map(function (PriceList $list) use ($editable) {
            $types = $list->customerTypes->pluck('name')->implode(', ');
            $label = $list->name.($list->is_active ? '' : ' (inactief)');

            $state = fn (Product $record) => PriceGrid::display($record->priceListItems->firstWhere('price_list_id', $list->id));

            if (! $editable) {
                return TextColumn::make("list_{$list->id}")
                    ->label($label)
                    ->state($state)
                    ->placeholder('—')
                    ->tooltip($types ?: null)
                    ->alignEnd();
            }

            return TextInputColumn::make("list_{$list->id}")
                ->label($label)
                ->tooltip($types ? "Voor: {$types}" : 'Aan geen klanttype gekoppeld')
                ->state($state)
                ->placeholder('—')
                ->rules([PriceGrid::RULE])
                ->validationMessages(['regex' => 'Typ een bedrag (bv. 24,50), "d" voor dagprijs, of laat leeg.'])
                ->updateStateUsing(function (Product $record, ?string $state) use ($list) {
                    PriceGrid::set($record, $list, $state);
                    $record->load('priceListItems');

                    return $state;
                })
                // Columns of inactive lists start hidden; the column picker shows them.
                ->toggleable(isToggledHiddenByDefault: ! $list->is_active)
                ->width('8rem')
                ->extraInputAttributes(['inputmode' => 'decimal', 'style' => 'text-align: right']);
        })->all();

        return $table
            ->query(Product::query()->with(['priceListItems', 'category']))
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->description(fn (Product $record) => collect([$record->category?->name, 'per '.$record->unit->short()])->filter()->implode(' · '))
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->weight('medium'),
                ...$listColumns,
            ])
            ->filters([
                SelectFilter::make('product_category_id')->label('Categorie')->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('Beschikbaar')->default(true),
                SelectFilter::make('missing')
                    ->label('Ontbreekt in lijst')
                    ->options(fn () => $this->lists()->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $listId) => $query->whereDoesntHave('priceListItems', fn (Builder $items) => $items->where('price_list_id', $listId)),
                    )),
            ])
            ->emptyStateHeading('Geen producten gevonden')
            ->emptyStateDescription($this->lists()->isEmpty() ? 'Maak eerst een prijslijst aan onder Catalogus → Prijslijsten.' : null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copy')
                ->label('Prijzen overnemen')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn () => static::canEdit() && $this->lists()->count() > 1)
                ->modalHeading('Prijzen overnemen van een andere lijst')
                ->modalDescription('Bv. Particulier = Zakelijk + 15%, afgerond op 10 cent. Producten die nog niet in de doellijst staan, worden toegevoegd. Dagprijzen blijven dagprijzen.')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('from')->label('Van lijst')->options(fn () => $this->lists()->pluck('name', 'id'))->required()->native(false),
                        Select::make('to')->label('Naar lijst')->options(fn () => $this->lists()->pluck('name', 'id'))->required()->different('from')->native(false),
                        TextInput::make('percentage')
                            ->label('Aanpassing')
                            ->helperText('15 = 15% duurder, -5 = 5% goedkoper, 0 = gelijk.')
                            ->numeric()
                            ->default(0)
                            ->suffix('%')
                            ->required(),
                        Select::make('step')
                            ->label('Afronden naar boven op')
                            ->options(['0' => 'De cent', '0.05' => '5 cent', '0.10' => '10 cent', '0.50' => '50 cent', '1' => 'Hele euro'])
                            ->default('0.10')
                            ->required()
                            ->native(false),
                    ]),
                    Radio::make('overwrite')
                        ->label('Producten die al een prijs hebben in de doellijst')
                        ->options(['0' => 'Laten staan', '1' => 'Overschrijven'])
                        ->default('0')
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $count = PriceGrid::copy(
                        PriceList::findOrFail($data['from']),
                        PriceList::findOrFail($data['to']),
                        (float) $data['percentage'],
                        (float) $data['step'],
                        (bool) $data['overwrite'],
                    );

                    Notification::make()->success()->title("{$count} prijs/prijzen overgenomen")->send();
                    $this->lists = null;
                }),
        ];
    }
}
