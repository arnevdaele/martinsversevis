<?php

namespace App\Mail;

use App\Mail\Concerns\RespectsMailQuota;
use App\Models\Order;
use App\Support\OrderHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To staff and the type's extra addresses, when a customer places, changes or
 * cancels an order. Reply goes straight to the customer.
 */
class OrderReceived extends Mailable implements ShouldQueue
{
    use Queueable, RespectsMailQuota, SerializesModels;

    public const PLACED = 'received';

    public const CHANGED = 'changed';

    public const CANCELLED = 'cancelled_by_customer';

    /** @param  list<array<string, mixed>>  $changes  from {@see OrderHistory::diff()}, for a changed order */
    public function __construct(public Order $order, public string $event = self::PLACED, public array $changes = []) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->order->customerUser?->email ?? $this->order->customer->email;

        return new Envelope(
            subject: __("orders.mail.{$this->event}.subject", [
                'number' => $this->order->number,
                'customer' => $this->order->customer->name,
            ]),
            replyTo: $replyTo ? [new Address($replyTo, $this->order->customer->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-received', with: [
            'order' => $this->order->loadMissing('items', 'customer.type', 'customerUser'),
            'event' => $this->event,
            'changeLines' => array_map(OrderHistory::describe(...), $this->changes),
            'url' => route('filament.admin.resources.orders.view', ['record' => $this->order]),
        ]);
    }
}
