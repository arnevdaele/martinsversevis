<?php

namespace App\Filament\Resources\DeliverySchedules\Pages;

use App\Filament\Resources\DeliverySchedules\DeliveryScheduleResource;
use App\Models\DeliverySchedule;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliverySchedule extends EditRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['days'] = DeliverySchedule::normaliseDays($data['days'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['days'] = DeliverySchedule::normaliseDays($data['days'] ?? []);

        return $data;
    }
}
