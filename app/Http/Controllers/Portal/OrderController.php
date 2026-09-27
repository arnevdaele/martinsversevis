<?php

namespace App\Http\Controllers\Portal;

use App\Actions\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\DeliveryCalendar;
use App\Support\Money;
use App\Support\PortalCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /** The order form: the customer's price lists, and optionally a previous order to start from. */
    public function create(Request $request): Response
    {
        $user = $this->user($request);
        $catalogue = PortalCatalogue::for($user->customer);

        return Inertia::render('Order/Create', [
            ...$catalogue,
            'reorder' => $this->reorderLines($request, $user, collect($catalogue['items'])),
            'delivery' => $this->delivery(DeliveryCalendar::for($user->customer)),
        ]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*' => ['numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $placeOrder->handle(
            $this->user($request),
            $data['lines'],
            $data['requested_delivery_date'] ?? null,
            $data['customer_note'] ?? null,
            $data['notes'] ?? [],
        );

        return redirect()
            ->route('portal.orders.show', $order)
            ->with('success', __('portal.order.placed', ['number' => $order->number]))
            ->with('orderPlaced', true);
    }

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('customer_id', $this->user($request)->customer_id)
            ->with('customerUser')
            ->withCount('items')
            ->latest('submitted_at')
            ->paginate(20)
            ->through(fn (Order $order) => $this->summary($order));

        return Inertia::render('Orders/Index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): Response
    {
        // Everyone at the same customer shares the order history; nobody else sees it.
        abort_unless($order->customer_id === $this->user($request)->customer_id, 404);

        $order->load('items', 'customerUser');

        return Inertia::render('Orders/Show', [
            'order' => [
                ...$this->summary($order),
                'customerNote' => $order->customer_note,
                'subtotal' => Money::format($order->subtotal),
                'vatTotal' => Money::format($order->vat_total),
                'hasUnpricedItems' => $order->has_unpriced_items,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'name' => $item->t('product_name'),
                    'note' => $item->note,
                    'quantity' => Money::quantity($item->quantity, $item->unit),
                    'unitPrice' => $item->unit_price === null ? null : Money::format($item->unit_price).' / '.$item->unit->short(),
                    'lineTotal' => $item->line_total === null ? null : Money::format($item->line_total),
                ])->all(),
            ],
            'justPlaced' => (bool) $request->session()->get('orderPlaced'),
        ]);
    }

    /** The date picker: open days with their deadlines, closures to warn about, the minimum. */
    private function delivery(DeliveryCalendar $calendar): array
    {
        $minimum = $calendar->minimumOrderAmount();

        return [
            'hasRules' => $calendar->hasRules(),
            'minDate' => today()->toDateString(),
            'options' => array_map(fn (array $option) => [
                'date' => $option['date']->toDateString(),
                'weekday' => Str::ucfirst($option['date']->translatedFormat('D')),
                'day' => $option['date']->translatedFormat('j M'),
                'deadline' => __('portal.order.delivery_until', [
                    'deadline' => $option['cutoff']->isSameDay($option['date'])
                        ? $option['cutoff']->format('H:i')
                        : $option['cutoff']->translatedFormat('D j M H:i'),
                ]),
                'extra' => $option['extra'],
                'reason' => $option['reason'],
            ], $calendar->options()),
            'closures' => $calendar->upcomingClosures()->map(fn ($closure) => trim(
                ($closure->starts_on->isSameDay($closure->ends_on)
                    ? __('portal.order.closure_day', ['date' => $closure->starts_on->translatedFormat('l j F')])
                    : __('portal.order.closure_range', [
                        'from' => $closure->starts_on->translatedFormat('j F'),
                        'until' => $closure->ends_on->translatedFormat('j F'),
                    ])).($closure->t('reason') ? ' — '.$closure->t('reason') : ''),
            ))->all(),
            'minimum' => $minimum,
            'minimumLabel' => $minimum === null ? null : __('portal.order.order_minimum', ['amount' => Money::format($minimum)]),
        ];
    }

    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'statusLabel' => $order->status->getLabel(),
            'submittedAt' => $order->submitted_at?->translatedFormat('j M Y, H:i'),
            'deliveryDate' => $order->requested_delivery_date?->translatedFormat('l j F'),
            'total' => Money::format($order->total),
            'itemsCount' => $order->items_count ?? $order->items->count(),
            'placedBy' => $order->customerUser?->name,
        ];
    }

    /**
     * "Order again": map the old order's products onto what the customer can
     * order today. Products that left their lists are silently dropped.
     *
     * @return array<int, float>|null price_list_item_id => quantity
     */
    private function reorderLines(Request $request, CustomerUser $user, Collection $items): ?array
    {
        $orderId = $request->integer('reorder');

        if (! $orderId) {
            return null;
        }

        $order = Order::with('items')->where('customer_id', $user->customer_id)->find($orderId);

        if (! $order) {
            return null;
        }

        $byListAndProduct = [];
        foreach ($order->items as $orderItem) {
            $byListAndProduct[$orderItem->price_list_id.':'.$orderItem->product_id] = (float) $orderItem->quantity;
        }

        $lines = [];
        foreach ($items as $item) {
            $key = $item['priceListId'].':'.$item['productId'];
            if (isset($byListAndProduct[$key])) {
                $lines[$item['id']] = $byListAndProduct[$key];
            }
        }

        return $lines;
    }

    private function user(Request $request): CustomerUser
    {
        return $request->user('customer');
    }
}
