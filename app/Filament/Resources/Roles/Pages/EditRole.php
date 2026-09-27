<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Support\PermissionMatrix;
use App\Support\Grants;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + PermissionMatrix::fill($this->record->permissions->pluck('name')->all());
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions(Grants::merge(
            $this->record->permissions->pluck('name')->all(),
            PermissionMatrix::selected($this->form->getRawState()),
            Grants::for(auth()->user())->permissions(),
        ));
    }
}
