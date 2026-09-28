@php
    use App\Enums\OrderStatus;
    use App\Support\DeliveryNotes;
    use App\Support\Money;

    $customer = $order->customer;
    // Until it's delivered, an unweighed line gets a blank to write the weight on.
    $asOrdered = $order->status === OrderStatus::Delivered;
@endphp
<section class="sheet">
    <div class="head">
        <div class="company">
            <strong>{{ $company['name'] }}</strong><br>
            @foreach ($company['address'] as $line)
                {{ $line }}<br>
            @endforeach
            @if ($company['vat_number'])
                {{ __('orders.delivery_note.our_vat') }} {{ $company['vat_number'] }}<br>
            @endif
            <span class="muted">{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</span>
        </div>
        <div class="doc">
            <h1>{{ __('orders.delivery_note.title') }}</h1>
            {{ __('orders.delivery_note.number') }} <strong>{{ $order->number }}</strong><br>
            @if ($order->requested_delivery_date)
                {{ __('orders.delivery_note.delivery_date', ['date' => $order->requested_delivery_date->translatedFormat('j F Y')]) }}<br>
            @endif
            <span class="muted">{{ __('orders.delivery_note.placed_at', ['date' => $order->created_at->translatedFormat('j F Y')]) }}</span>
        </div>
    </div>

    <div class="customer">
        <strong>{{ $customer->name }}</strong><br>
        @if ($customer->contact_name)
            {{ $customer->contact_name }}<br>
        @endif
        @if ($customer->street)
            {{ $customer->street }}<br>
        @endif
        @if ($customer->postal_code || $customer->city)
            {{ trim("{$customer->postal_code} {$customer->city}") }}<br>
        @endif
        @if ($customer->vat_number)
            {{ __('orders.delivery_note.customer_vat') }} {{ $customer->vat_number }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('orders.fields.product') }}</th>
                <th class="num">{{ __('orders.delivery_note.ordered') }}</th>
                <th class="num">{{ __('orders.delivery_note.delivered') }}</th>
                <th class="num">{{ __('orders.fields.unit_price') }}</th>
                <th class="num">{{ __('orders.delivery_note.vat_rate') }}</th>
                <th class="num">{{ __('orders.fields.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->t('product_name') }}
                        @if ($item->sku)<span class="small">· {{ $item->sku }}</span>@endif
                        @if ($item->note)<br><span class="small">{{ $item->note }}</span>@endif
                    </td>
                    <td class="num">{{ Money::quantity($item->quantity, $item->unit) }}</td>
                    <td class="num">
                        @if ($item->delivered_quantity !== null || $asOrdered)
                            <strong>{{ Money::quantity($item->billedQuantity(), $item->unit) }}</strong>
                        @else
                            <span class="blank"></span>
                        @endif
                    </td>
                    <td class="num">{{ $item->unit_price === null ? __('orders.day_price') : Money::format($item->unit_price).' / '.$item->unit->short() }}</td>
                    <td class="num">{{ DeliveryNotes::rate($item->vat_rate) }}</td>
                    <td class="num">{{ $item->line_total === null ? '—' : Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            @foreach ($order->vatBreakdown() as $rate => $amounts)
                <tr>
                    <td>{{ __('orders.delivery_note.base', ['rate' => DeliveryNotes::rate($rate)]) }}</td>
                    <td class="num">{{ Money::format($amounts['base']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('orders.delivery_note.vat_amount', ['rate' => DeliveryNotes::rate($rate)]) }}</td>
                    <td class="num">{{ Money::format($amounts['vat']) }}</td>
                </tr>
            @endforeach
            <tr>
                <td>{{ __('orders.fields.subtotal') }}</td>
                <td class="num">{{ Money::format($order->subtotal) }}</td>
            </tr>
            <tr>
                <td>{{ __('orders.fields.vat') }}</td>
                <td class="num">{{ Money::format($order->vat_total) }}</td>
            </tr>
            <tr class="grand">
                <td>{{ __('orders.fields.total') }}</td>
                <td class="num">{{ Money::format($order->total) }}</td>
            </tr>
        </table>
    </div>

    @if ($order->has_unpriced_items)
        <p class="notice">{{ __('orders.unpriced_notice') }}</p>
    @endif

    <div class="sign">
        <div>{{ __('orders.delivery_note.received_by') }}</div>
        <div>{{ __('orders.delivery_note.signature') }}</div>
    </div>
</section>
