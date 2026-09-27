@php
    use App\Support\Money;

    // Markdown tables break on newlines and on a stray pipe, so each row is built here in one line.
    $cell = fn (?string $text) => str_replace(['|', "\n", "\r"], ['/', ' ', ''], (string) $text);
@endphp
<x-mail::table>
| {{ __('orders.fields.product') }} | {{ __('orders.fields.quantity') }} | {{ __('orders.fields.unit_price') }} | {{ __('orders.fields.line_total') }} |
|:--|--:|--:|--:|
@foreach ($order->items as $item)
| {{ $cell($item->t('product_name').($item->note ? ' — '.$item->note : '')) }} | {{ Money::quantity($item->quantity, $item->unit) }} | {{ $item->unit_price === null ? __('orders.day_price') : Money::format($item->unit_price) }} | {{ $item->line_total === null ? '—' : Money::format($item->line_total) }} |
@endforeach
</x-mail::table>

**{{ __('orders.fields.subtotal') }}:** {{ Money::format($order->subtotal) }}<br>
**{{ __('orders.fields.vat') }}:** {{ Money::format($order->vat_total) }}<br>
**{{ __('orders.fields.total') }}:** {{ Money::format($order->total) }}

@if ($order->has_unpriced_items)
_{{ __('orders.unpriced_notice') }}_
@endif
