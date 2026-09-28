<x-mail::message>
# {{ __($changed ? 'orders.mail.confirmation.changed_heading' : 'orders.mail.confirmation.heading') }}

{{ __($changed ? 'orders.mail.confirmation.changed_intro' : 'orders.mail.confirmation.intro', ['number' => $order->number]) }}

@if ($order->requested_delivery_date)
**{{ __('orders.fields.requested_delivery_date') }}:** {{ $order->requested_delivery_date->translatedFormat('l j F Y') }}
@endif

@include('mail._order-lines')

<x-mail::button :url="$url">
{{ __('orders.mail.confirmation.action') }}
</x-mail::button>
</x-mail::message>
