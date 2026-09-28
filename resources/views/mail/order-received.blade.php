<x-mail::message>
# {{ __("orders.mail.{$event}.heading", ['number' => $order->number]) }}

@if ($event !== 'received')
{{ __("orders.mail.{$event}.intro") }}

@endif
**{{ __('orders.fields.customer') }}:** {{ $order->customer->name }} ({{ $order->customer->type->name }})
@if ($order->customerUser)
**{{ __('orders.fields.placed_by') }}:** {{ $order->customerUser->name }} — {{ $order->customerUser->email }}
@endif
@if ($order->requested_delivery_date)
**{{ __('orders.fields.requested_delivery_date') }}:** {{ $order->requested_delivery_date->translatedFormat('l j F Y') }}
@endif
@if ($order->customer->address())
**{{ __('orders.fields.address') }}:** {{ $order->customer->address() }}
@endif

@if ($order->customer_note)
<x-mail::panel>
{{ $order->customer_note }}
</x-mail::panel>
@endif

@if ($changeLines !== [])
**{{ __('orders.mail.changed.changes') }}**

@foreach ($changeLines as $change)
- {{ $change }}
@endforeach

@endif
@if ($event !== 'cancelled_by_customer')
@include('mail._order-lines')
@endif

<x-mail::button :url="$url">
{{ __('orders.mail.received.action') }}
</x-mail::button>
</x-mail::message>
