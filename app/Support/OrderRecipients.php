<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

/**
 * Who hears about a new order: every active staff member holding
 * `orders.receive-notifications` who may see the customer's type, plus the
 * extra addresses configured on that type.
 */
final class OrderRecipients
{
    /** @return list<string> lower-cased, unique e-mail addresses */
    public static function for(Order $order): array
    {
        $typeId = $order->customer->customer_type_id;

        $staff = User::permission('orders.receive-notifications')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canSeeCustomerType($typeId))
            ->pluck('email');

        $extra = collect($order->customer->type->notification_emails ?? []);

        return $staff->merge($extra)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
