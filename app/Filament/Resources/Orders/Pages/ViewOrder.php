<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Widgets\OrderHistory;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
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

    protected function getFooterWidgets(): array
    {
        return [OrderHistory::class];
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
            // Only a status the customer hears about asks anything; the rest is one click.
            ->modalHeading(fn (Order $record) => ChangeOrderStatus::canNotify($record, $to) ? "Bestelling {$record->number}: {$to->getLabel()}" : null)
            ->modalSubmitActionLabel($label)
            ->schema(fn (Order $record) => ChangeOrderStatus::canNotify($record, $to) ? [
                Toggle::make('notify')
                    ->label("Klant verwittigen per e-mail ({$record->customerUser->email})")
                    ->helperText(match (true) {
                        ! $record->customerUser->receives_order_confirmations => 'Deze klant heeft e-mails over bestellingen uitgeschakeld.',
                        $to === OrderStatus::Confirmed && $record->has_unpriced_items => 'Let op: nog niet alle dagprijzen zijn ingevuld. De klant ziet dan "dagprijs" bij die lijnen.',
                        $to === OrderStatus::Confirmed => 'Met de definitieve lijnen, prijzen en het totaal.',
                        default => null,
                    })
                    ->default($record->customerUser->receives_order_confirmations)
                    ->live(),
                Textarea::make('note')
                    ->label('Bericht aan de klant (optioneel)')
                    ->placeholder($to === OrderStatus::Cancelled ? 'Bv. waarom de bestelling niet doorgaat.' : 'Bv. een product dat vervangen werd.')
                    ->rows(3)
                    ->maxLength(1000)
                    ->visible(fn (Get $get) => (bool) $get('notify')),
            ] : [])
            ->action(function (Order $record, array $data) use ($to) {
                $sent = app(ChangeOrderStatus::class)->handle($record, $to, auth()->user(), (bool) ($data['notify'] ?? false), $data['note'] ?? null);
                $this->refreshFormData(['status', 'handled_by']);
                $this->dispatch('refresh-order-totals');

                Notification::make()
                    ->success()
                    ->title("Status gewijzigd naar: {$to->getLabel()}")
                    ->body($sent ? 'De klant krijgt een e-mail.' : null)
                    ->send();
            });
    }
}
