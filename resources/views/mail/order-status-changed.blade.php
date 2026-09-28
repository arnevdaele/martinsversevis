<x-mail::message>
# {{ __("orders.mail.{$status}.heading") }}

{{ __("orders.mail.{$status}.intro", ['number' => $order->number]) }}

@if ($order->requested_delivery_date && $status === 'confirmed')
**{{ __('orders.fields.requested_delivery_date') }}:** {{ $order->requested_delivery_date->translatedFormat('l j F Y') }}
@endif

@if (filled($note))
<x-mail::panel>
{{ $note }}
</x-mail::panel>
@endif

@if ($status === 'confirmed')
@include('mail._order-lines')
@endif

<x-mail::button :url="$url">
{{ __('orders.mail.confirmation.action') }}
</x-mail::button>
</x-mail::message>
