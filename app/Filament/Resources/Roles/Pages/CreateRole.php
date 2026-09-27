<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Support\PermissionMatrix;
use App\Support\Grants;
use App\Support\Permissions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['guard_name' => Permissions::GUARD];
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions(Grants::merge(
            [],
            PermissionMatrix::selected($this->form->getRawState()),
            Grants::for(auth()->user())->permissions(),
        ));
    }
}
