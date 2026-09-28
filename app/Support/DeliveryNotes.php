<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Traits\Localizable;

/**
 * The leveringsbon: what went out, as weighed, with prices and VAT per rate.
 * One printable page for a single order (admin and portal) or for a whole
 * delivery day (admin), each note in its customer's language.
 */
class DeliveryNotes
{
    use Localizable;

    /**
     * @param  iterable<Order>  $orders
     * @param  string|null  $locale  null = each customer's own language
     */
    public function render(iterable $orders, string $title, ?string $locale = null): string
    {
        $sheets = [];
        $company = CompanyDetails::get();

        foreach ($orders as $order) {
            $order->loadMissing('items', 'customer', 'customerUser.customer');

            $sheets[] = $this->withLocale($locale ?? self::localeFor($order), fn () => view('documents._delivery-note', [
                'order' => $order,
                'company' => $company,
            ])->render());
        }

        return view('documents.delivery-notes', ['sheets' => $sheets, 'title' => $title])->render();
    }

    public static function localeFor(Order $order): string
    {
        if ($order->customerUser) {
            return $order->customerUser->preferredLocale();
        }

        return Locales::supports($order->customer->locale) ? $order->customer->locale : Locales::default();
    }

    /** "6.00" → "6%", "5.50" → "5,5%". */
    public static function rate(string|float $rate): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.')).'%';
    }
}
