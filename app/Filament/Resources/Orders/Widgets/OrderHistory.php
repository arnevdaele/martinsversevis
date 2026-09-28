<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/** Who changed what on this order, newest first. See {@see \App\Support\OrderHistory}. */
class OrderHistory extends Widget
{
    /** @var Order|null */
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.resources.order-history';

    /** Items, weights and status changed elsewhere on the page; show the new entry. */
    #[On('refresh-order-totals')]
    public function refresh(): void {}

    protected function getViewData(): array
    {
        return ['events' => $this->record?->events()->get() ?? collect()];
    }
}
