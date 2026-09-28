<?php

namespace App\Filament\Resources\FailedJobs\Pages;

use App\Filament\Resources\FailedJobs\FailedJobResource;
use App\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageFailedJobs extends ManageRecords
{
    protected static string $resource = FailedJobResource::class;

    protected ?string $subheading = 'Wat hier staat, is nooit bij de ontvanger aangekomen. Los de oorzaak op (bv. de mailinstellingen) en verstuur opnieuw.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryAll')
                ->label('Alles opnieuw versturen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->visible(fn () => FailedJobResource::canRetry() && FailedJob::exists())
                ->action(fn () => FailedJobResource::retry(FailedJob::all())),
        ];
    }
}
