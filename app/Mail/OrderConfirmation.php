<?php

namespace App\Mail;

use App\Mail\Concerns\RespectsMailQuota;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the person who placed the order: "we have it". */
class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, RespectsMailQuota, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('orders.mail.confirmation.subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-confirmation', with: [
            'order' => $this->order->loadMissing('items', 'customer', 'customerUser'),
            'url' => route('portal.orders.show', $this->order),
        ]);
    }
}
