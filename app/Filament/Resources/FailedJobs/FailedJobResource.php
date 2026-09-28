<?php

namespace App\Filament\Resources\FailedJobs;

use App\Filament\Resources\FailedJobs\Pages\ManageFailedJobs;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Resource;
use App\Models\FailedJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Mails that gave up after their retries (SMTP down for hours, a bounced
 * address…). Without this page they sat in `failed_jobs` unseen until pruned.
 */
class FailedJobResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Mislukte e-mails';

    protected static ?string $modelLabel = 'mislukte e-mail';

    protected static ?string $pluralModelLabel = 'mislukte e-mails';

    public static function getNavigationBadge(): ?string
    {
        $count = FailedJob::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canRetry(): bool
    {
        return auth()->user()?->can('retry', FailedJob::class) ?? false;
    }

    /** Puts the mails back on the queue; `queue:retry` removes them from this list. */
    public static function retry(Collection $records): void
    {
        Artisan::call('queue:retry', ['id' => $records->pluck('uuid')->all()]);

        Notification::make()
            ->success()
            ->title($records->count() === 1 ? 'Opnieuw in de wachtrij gezet' : "{$records->count()} e-mails opnieuw in de wachtrij gezet")
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('failed_at')->label('Mislukt op')->dateTime('D d/m/Y H:i')->sortable(),
                TextColumn::make('type')
                    ->label('Soort')
                    ->state(fn (FailedJob $record) => $record->type())
                    ->weight('medium'),
                TextColumn::make('recipient')
                    ->label('Aan')
                    ->state(fn (FailedJob $record) => $record->recipient())
                    ->placeholder('onbekend'),
                TextColumn::make('order')
                    ->label('Bestelling')
                    ->state(fn (FailedJob $record) => $record->order()?->number)
                    ->url(fn (FailedJob $record) => ($order = $record->order()) ? OrderResource::getUrl('view', ['record' => $order]) : null)
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('exception')
                    ->label('Foutmelding')
                    ->state(fn (FailedJob $record) => $record->reason())
                    ->color('gray')
                    ->wrap()
                    ->lineClamp(2),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Opnieuw versturen')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn () => static::canRetry())
                    ->action(fn (FailedJob $record) => static::retry(collect([$record]))),
                DeleteAction::make()->modalDescription('De e-mail wordt niet meer verstuurd.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retry')
                        ->label('Opnieuw versturen')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn () => static::canRetry())
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => static::retry($records)),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Geen mislukte e-mails')
            ->emptyStateDescription('E-mails die na 12 uur nog altijd niet verstuurd raakten, verschijnen hier. Ze blijven 30 dagen staan.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageFailedJobs::route('/')];
    }
}
