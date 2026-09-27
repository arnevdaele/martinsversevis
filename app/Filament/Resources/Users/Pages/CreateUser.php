<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\Concerns\SyncsAccess;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsAccess;

    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $this->syncAccess($this->record);
    }
}
