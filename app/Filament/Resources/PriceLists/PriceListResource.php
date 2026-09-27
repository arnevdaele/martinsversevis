<?php

namespace App\Filament\Resources\PriceLists;

use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Filament\Resources\PriceLists\Pages\ManagePrices;
use App\Filament\Resources\PriceLists\Schemas\PriceListForm;
use App\Filament\Resources\PriceLists\Tables\PriceListsTable;
use App\Filament\Support\Resource;
use App\Models\PriceList;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogus';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Prijslijsten';

    protected static ?string $modelLabel = 'prijslijst';

    protected static ?string $pluralModelLabel = 'prijslijsten';

    protected static ?string $recordTitleAttribute = 'name';

    /** Prices | Settings as tabs above the page, so the price table keeps the full width. */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'prices' => ManagePrices::route('/{record}'),
            'edit' => EditPriceList::route('/{record}/settings'),
        ];
    }

    /** Two tabs on every list: its prices (where you land) and its settings. */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([ManagePrices::class, EditPriceList::class]);
    }
}
