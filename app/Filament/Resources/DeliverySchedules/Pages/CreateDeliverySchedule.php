<?php

namespace App\Filament\Resources\DeliverySchedules\Pages;

use App\Filament\Resources\DeliverySchedules\DeliveryScheduleResource;
use App\Models\DeliverySchedule;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliverySchedule extends CreateRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['days'] = DeliverySchedule::normaliseDays($data['days'] ?? []);

        return $data;
    }
}
