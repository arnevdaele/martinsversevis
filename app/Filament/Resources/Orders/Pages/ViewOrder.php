<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return "Bestelling {$this->record->number}";
    }

    /** The items table recalculated the totals; show the new numbers. */
    #[On('refresh-order-totals')]
    public function refreshTotals(): void
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction(OrderStatus::Confirmed, 'Bevestigen', 'heroicon-o-check', [OrderStatus::New]),
            $this->statusAction(OrderStatus::Delivered, 'Geleverd', 'heroicon-o-truck', [OrderStatus::New, OrderStatus::Confirmed]),
            EditAction::make()->label('Bewerken')->color('gray'),
            ActionGroup::make([
                $this->statusAction(OrderStatus::Cancelled, 'Annuleren', 'heroicon-o-x-mark', [OrderStatus::New, OrderStatus::Confirmed])
                    ->color('danger')
                    ->requiresConfirmation(),
                DeleteAction::make(),
            ]),
        ];
    }

    /** @param  list<OrderStatus>  $from */
    private function statusAction(OrderStatus $to, string $label, string $icon, array $from): Action
    {
        return Action::make("status-{$to->value}")
            ->label($label)
            ->icon($icon)
            ->color($to->getColor())
            ->visible(fn (Order $record) => in_array($record->status, $from, true) && auth()->user()->can('update', $record))
            ->action(function (Order $record) use ($to) {
                $record->update(['status' => $to, 'handled_by' => $record->handled_by ?? auth()->id()]);
                $this->refreshFormData(['status', 'handled_by']);
            })
            ->successNotificationTitle("Status gewijzigd naar: {$to->getLabel()}");
    }
}
