<?php

namespace App\Filament\Resources\DeliveryExceptions\Pages;

use App\Filament\Resources\DeliveryExceptions\DeliveryExceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryExceptions extends ManageRecords
{
    protected static string $resource = DeliveryExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Sluiting of extra dag toevoegen')];
    }
}
