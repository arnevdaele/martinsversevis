@php
    use App\Actions\WeighOrderItem;
    use App\Enums\OrderStatus;
    use App\Filament\Resources\Orders\OrderResource;
    use App\Support\Money;
@endphp

<x-filament-panels::page>
    {{-- No custom admin theme, so this page brings its own few rules. --}}
    <style>
        .pl-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.5rem; }
        .pl-date { width: 16rem; max-width: 100%; }
        .pl-weigh { display: inline-flex; align-items: center; gap: .25rem; white-space: nowrap; }
        .pl-weigh input { width: 5rem; text-align: right; border-radius: .5rem; border: 1px solid var(--gray-300); padding: .25rem .5rem; background: transparent; color: inherit; font-variant-numeric: tabular-nums; }
        .dark .pl-weigh input { border-color: var(--gray-700); }
        .pl-stat { font-size: .875rem; color: var(--gray-500); }
        .pl-stat strong { font-size: 1.25rem; color: var(--gray-950); margin-right: .25rem; }
        .dark .pl-stat strong { color: #fff; }
        .pl-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .pl-table th { text-align: left; font-weight: 600; padding: .5rem .75rem; border-bottom: 1px solid var(--gray-200); }
        .pl-table td { padding: .5rem .75rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .dark .pl-table th, .dark .pl-table td { border-color: var(--gray-800); }
        .pl-table .pl-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .pl-table .pl-qty { font-weight: 600; }
        .pl-cat { margin: 1.25rem 0 .25rem; font-size: .75rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--gray-500); }
        .pl-cat:first-child { margin-top: 0; }
        .pl-muted { color: var(--gray-500); }
        .pl-orders { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr)); }
        .pl-order-head { display: flex; justify-content: space-between; gap: .75rem; align-items: start; }
        .pl-order-head a { font-weight: 600; color: var(--primary-600); }
        .pl-note { margin-top: .5rem; padding: .5rem .75rem; border-radius: .5rem; font-size: .875rem; background: var(--gray-50); }
        .dark .pl-note { background: var(--gray-800); }
        .pl-items { margin-top: .75rem; }
    </style>

    <div class="pl-bar">
        <div class="pl-date">{{ $this->form }}</div>
        <span class="pl-stat"><strong>{{ $list->orders->count() }}</strong> bestelling(en)</span>
        <span class="pl-stat"><strong>{{ $list->orders->pluck('customer_id')->unique()->count() }}</strong> klant(en)</span>
    </div>

    @if ($list->unconfirmed() > 0)
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle"
            heading="{{ $list->unconfirmed() }} bestelling(en) voor deze dag zijn nog niet bevestigd"
            description="Ze staan wel in de lijsten hieronder." />
    @endif

    @if ($withoutDate > 0)
        <x-filament::callout color="info" icon="heroicon-o-information-circle"
            heading="{{ $withoutDate }} open bestelling(en) zonder leverdatum"
            description="Die staan in geen enkel dagoverzicht; geef ze een datum in de bestelling." />
    @endif

    @if ($list->orders->isEmpty())
        <x-filament::section>
            <p class="pl-muted">Geen bestellingen om te leveren op deze dag.</p>
        </x-filament::section>
    @else
        <x-filament::section heading="Inkooplijst" description="Alles wat die dag vertrekt, samengeteld over alle bestellingen.">
            @foreach ($totals as $category => $lines)
                <p class="pl-cat">{{ $category }}</p>
                <table class="pl-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="pl-num">Totaal</th>
                            <th class="pl-num">Bestellingen</th>
                            <th>Opmerkingen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td>{{ $line['name'] }}</td>
                                <td class="pl-num pl-qty">{{ $line['quantity'] }}</td>
                                <td class="pl-num">{{ $line['orders'] }}</td>
                                <td class="pl-muted">{!! collect($line['notes'])->map(fn ($note) => e($note))->implode('<br>') !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </x-filament::section>

        <x-filament::section heading="Per klant" description="Om in te pakken; de afdrukversie zet elke klant op een eigen blad.">
            <div class="pl-orders">
                @foreach ($list->orders as $order)
                    <x-filament::section compact>
                        <div class="pl-order-head">
                            <div>
                                <div style="font-weight: 600;">{{ $order->customer->name }}</div>
                                <div class="pl-muted" style="font-size: .875rem;">
                                    <a href="{{ OrderResource::getUrl('view', ['record' => $order]) }}">{{ $order->number }}</a>
                                    @if ($order->customer->address()) · {{ $order->customer->address() }} @endif
                                </div>
                            </div>
                            <x-filament::badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-filament::badge>
                        </div>

                        @if ($order->customer->delivery_instructions)
                            <div class="pl-note"><strong>Levering:</strong> {{ $order->customer->delivery_instructions }}</div>
                        @endif
                        @if ($order->customer_note)
                            <div class="pl-note"><strong>Opmerking klant:</strong> {{ $order->customer_note }}</div>
                        @endif

                        @php($canWeigh = auth()->user()->can('update', $order))
                        <table class="pl-table pl-items">
                            @foreach ($order->items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td class="pl-num pl-qty" style="width: 6rem;">{{ Money::quantity($item->quantity, $item->unit, 'nl') }}</td>
                                    <td>
                                        {{ $item->product_name }}
                                        @if ($item->note)<div class="pl-muted">{{ $item->note }}</div>@endif
                                    </td>
                                    <td class="pl-num">
                                        @if ($canWeigh)
                                            <label class="pl-weigh" title="Gewogen / geleverd; leeg = zoals besteld">
                                                <input type="text" inputmode="decimal" aria-label="Geleverd {{ $item->product_name }}"
                                                    value="{{ WeighOrderItem::format($item->delivered_quantity) }}"
                                                    placeholder="{{ WeighOrderItem::format($item->quantity) }}"
                                                    wire:change="weigh({{ $item->id }}, $event.target.value)">
                                                <span class="pl-muted">{{ $item->unit->short() }}</span>
                                            </label>
                                        @elseif ($item->delivered_quantity !== null)
                                            <span class="pl-muted">geleverd {{ Money::quantity($item->delivered_quantity, $item->unit, 'nl') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </x-filament::section>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
