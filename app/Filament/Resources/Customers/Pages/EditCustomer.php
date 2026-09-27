<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        // Soft delete only: orders keep pointing at the customer. There is no
        // force delete, because a customer with order history must survive.
        return [
            DeleteAction::make()->label('Archiveren'),
            RestoreAction::make(),
        ];
    }
}
