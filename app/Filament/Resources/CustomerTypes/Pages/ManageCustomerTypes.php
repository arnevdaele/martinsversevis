<?php

namespace App\Filament\Resources\CustomerTypes\Pages;

use App\Filament\Resources\CustomerTypes\CustomerTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerTypes extends ManageRecords
{
    protected static string $resource = CustomerTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nieuw klanttype'),
        ];
    }
}
