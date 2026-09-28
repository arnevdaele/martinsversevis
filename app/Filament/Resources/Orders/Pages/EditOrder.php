<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OrderEvent;
use App\Support\OrderHistory;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /** The order as it was before this save, for the history. */
    private ?array $before = null;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function beforeSave(): void
    {
        $this->before = OrderHistory::snapshot($this->record->fresh());
    }

    protected function afterSave(): void
    {
        $changes = OrderHistory::diff($this->before ?? [], OrderHistory::snapshot($this->record));
        OrderHistory::record($this->record, OrderEvent::EDITED, auth()->user(), $changes);
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }
}
