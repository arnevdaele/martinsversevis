<?php

namespace App\Filament\Resources\PriceLists\Pages;

use App\Filament\Resources\PriceLists\PriceListResource;
use App\Models\PriceList;
use App\Support\ListPrices;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceList extends CreateRecord
{
    protected static string $resource = PriceListResource::class;

    protected function afterCreate(): void
    {
        $start = $this->form->getRawState()['start'] ?? [];

        if (filled($start['from'] ?? null)) {
            ListPrices::copy(
                PriceList::findOrFail($start['from']),
                $this->record,
                (float) ($start['percentage'] ?? 0),
                (float) ($start['step'] ?? 0),
                overwrite: true,
            );
        }
    }

    /** Straight to the prices: that is what a new list is for. */
    protected function getRedirectUrl(): string
    {
        return PriceListResource::getUrl('prices', ['record' => $this->record]);
    }
}
