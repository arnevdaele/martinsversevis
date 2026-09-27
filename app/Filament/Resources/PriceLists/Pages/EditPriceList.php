<?php

namespace App\Filament\Resources\PriceLists\Pages;

use App\Filament\Resources\PriceLists\PriceListResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected static ?string $navigationLabel = 'Instellingen';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public function getBreadcrumb(): string
    {
        return 'Instellingen';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
