<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Mail\Concerns\RespectsMailQuota;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the person who placed the order: staff confirmed or cancelled it. Sent
 * with the final lines, so day prices filled in by then show up too.
 */
class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, RespectsMailQuota, SerializesModels;

    /** The statuses a customer hears about. */
    public const STATUSES = [OrderStatus::Confirmed, OrderStatus::Cancelled];

    public function __construct(public Order $order, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        $replyTo = config('mail.customer_reply_to');

        return new Envelope(
            subject: __("orders.mail.{$this->order->status->value}.subject", ['number' => $this->order->number]),
            replyTo: filled($replyTo['address']) ? [new Address($replyTo['address'], $replyTo['name'])] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-status-changed', with: [
            'order' => $this->order->loadMissing('items', 'customer', 'customerUser'),
            'status' => $this->order->status->value,

            'url' => route('portal.orders.show', $this->order),
        ]);
    }
}
