<?php

namespace App\Filament\Resources\PriceLists\RelationManagers;

use App\Filament\Support\Translations;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rules\Unique;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Producten & prijzen';

    protected static ?string $modelLabel = 'product';

    protected static ?string $pluralModelLabel = 'producten';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        PriceListItem::class,
                        'product_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('price_list_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages(['unique' => 'Dit product staat al in deze prijslijst.'])
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Prijs per eenheid (excl. btw)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->helperText('Leeg laten = dagprijs.'),
                TextInput::make('min_quantity')->label('Minimum hoeveelheid')->numeric()->minValue(0),
                TextInput::make('note')->label('Opmerking in het portaal')->placeholder('bv. enkel op vrijdag')->maxLength(255)->columnSpanFull(),
                Translations::section(fn (string $locale) => [
                    TextInput::make(Translations::field($locale, 'note'))->label('Opmerking')->maxLength(255),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        $editable = ! $this->isReadOnly();

        return $table
            ->recordTitleAttribute('product.name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product.category'))
            ->reorderable('sort_order', $editable)
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->description(fn (PriceListItem $record) => $record->note)
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('product.category.name')->label('Categorie')->badge()->color('gray')->placeholder('—')->toggleable(),
                TextColumn::make('product.unit')->label('Eenheid')->badge(),
                // Inline, so updating this week's prices is a single pass down the column.
                $editable
                    ? TextInputColumn::make('price')
                        ->label('Prijs (€)')
                        ->type('number')
                        ->step('0.01')
                        ->rules(['nullable', 'numeric', 'min:0'])
                        ->placeholder('Dagprijs')
                        ->width('9rem')
                    : TextColumn::make('price')->label('Prijs')->formatStateUsing(fn ($state) => Money::format($state))->placeholder('Dagprijs'),
                TextColumn::make('min_quantity')
                    ->label('Min.')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Categorie')
                    ->relationship('product.category', 'name'),
            ])
            ->headerActions([
                Action::make('addMany')
                    ->label('Producten toevoegen')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('products')
                            ->label('Producten')
                            ->multiple()
                            ->options(fn () => Product::query()
                                ->where('is_active', true)
                                ->whereNotIn('id', $this->getOwnerRecord()->items()->select('product_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('Ze komen in de lijst zonder prijs (dagprijs); vul de prijzen daarna in de tabel in.'),
                    ])
                    ->action(function (array $data) {
                        /** @var PriceList $list */
                        $list = $this->getOwnerRecord();
                        $next = (int) $list->items()->max('sort_order');

                        foreach ($data['products'] as $productId) {
                            $list->items()->firstOrCreate(['product_id' => $productId], ['sort_order' => ++$next]);
                        }

                        Notification::make()->success()->title(count($data['products']).' product(en) toegevoegd')->send();
                    }),
                CreateAction::make()->label('Eén product')->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Uit lijst'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('adjust')
                        ->label('Prijzen aanpassen met %')
                        ->icon(Heroicon::OutlinedArrowTrendingUp)
                        ->schema([
                            TextInput::make('percentage')
                                ->label('Percentage')
                                ->helperText('Bv. 5 voor +5%, -3 voor -3%. Dagprijzen blijven dagprijzen.')
                                ->numeric()
                                ->required()
                                ->suffix('%'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $factor = 1 + ((float) $data['percentage'] / 100);

                            $records->each(function (PriceListItem $item) use ($factor) {
                                if ($item->price !== null) {
                                    $item->update(['price' => round((float) $item->price * $factor, 2)]);
                                }
                            });

                            Notification::make()->success()->title('Prijzen aangepast')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()->label('Uit lijst halen'),
                ]),
            ])
            ->emptyStateHeading('Nog geen producten in deze lijst')
            ->emptyStateDescription('Klik op "Producten toevoegen" om er meerdere tegelijk in te zetten.');
    }
}
