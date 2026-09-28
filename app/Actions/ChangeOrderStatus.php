<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Staff move an order along. Confirming or cancelling can tell the customer;
 * the admin decides per order, defaulting to the login's own mail preference.
 */
class ChangeOrderStatus
{
    /** Whether this status change is something the customer can be told about. */
    public static function canNotify(Order $order, OrderStatus $to): bool
    {
        return in_array($to, OrderStatusChanged::STATUSES, true)
            && $order->customerUser !== null
            && $order->customerUser->is_active;
    }

    /** @return bool whether a mail went out */
    public function handle(Order $order, OrderStatus $to, User $by, bool $notify = false, ?string $note = null): bool
    {
        $order->update(['status' => $to, 'handled_by' => $order->handled_by ?? $by->id]);

        if (! $notify || ! self::canNotify($order, $to)) {
            return false;
        }

        // Passing the model (not the address) picks up the customer's language.
        Mail::to($order->customerUser)->queue(new OrderStatusChanged($order, filled($note) ? trim($note) : null));

        return true;
    }
}
