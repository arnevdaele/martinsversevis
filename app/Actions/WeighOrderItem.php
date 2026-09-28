<?php

namespace App\Actions;

use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\OrderHistory;
use Illuminate\Validation\ValidationException;

/**
 * Staff fill in what actually went out: "2,35" kg of a 2 kg order. The
 * delivered quantity is what gets billed; empty means "as ordered".
 */
class WeighOrderItem
{
    /** Accepts "2,35" as well as "2.35"; up to three decimals, like the column. */
    public const RULE = 'regex:/^\s*\d{1,7}([.,]\d{1,3})?\s*$/';

    public function handle(OrderItem $item, mixed $input, User $by): void
    {
        $value = self::parse($input);
        $before = OrderHistory::line($item);

        $item->update(['delivered_quantity' => $value]);

        $order = $item->loadMissing('order')->order;
        $order->unsetRelation('items');
        $order->recalculate();

        OrderHistory::record($order, OrderEvent::LINES, $by, OrderHistory::lineDiff($before, OrderHistory::line($item)));
    }

    public static function parse(mixed $input): ?string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        if (! preg_match('/^\d{1,7}([.,]\d{1,3})?$/', $input)) {
            throw ValidationException::withMessages(['delivered_quantity' => 'Vul een gewicht in zoals 2,35.']);
        }

        return str_replace(',', '.', $input);
    }

    /** "2.350" → "2,35", for an input field staff type Dutch numbers into. */
    public static function format(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_replace('.', ',', rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.'));
    }
}
