<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Support\DeliveryNotes;
use App\Support\PickingList;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Delivery notes to print: one order, or every order for a delivery day. */
class PrintDeliveryNotes
{
    public function order(Request $request, Order $order, DeliveryNotes $notes): Response
    {
        abort_unless($request->user()->can('view', $order), 403);

        return response($notes->render([$order], "Leveringsbon {$order->number}"));
    }

    public function day(Request $request, DeliveryNotes $notes): Response
    {
        abort_unless($request->user()->can('viewAny', Order::class), 403);

        $validated = $request->validate(['date' => ['required', 'date']]);
        $date = CarbonImmutable::parse($validated['date'])->startOfDay();
        $orders = PickingList::for($date, $request->user())->orders;

        abort_if($orders->isEmpty(), 404);

        return response($notes->render($orders, 'Leveringsbonnen '.$date->format('d-m-Y')));
    }
}
