<?php

namespace App\Http\Controllers\Portal;

use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\DeliveryCalendar;
use App\Support\DeliveryNotes;
use App\Support\Money;
use App\Support\OrderChanges;
use App\Support\PortalCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /** The order form: the customer's price lists, and optionally a previous order to start from. */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->user($request);
        $catalogue = PortalCatalogue::for($user->customer);
        $items = collect($catalogue['items']);

        $editing = null;
        if ($request->filled('edit')) {
            $order = Order::with('items', 'customer')->where('customer_id', $user->customer_id)->findOrFail($request->integer('edit'));

            if (! OrderChanges::allowed($order)) {
                return redirect()->route('portal.orders.show', $order)->with('error', __('orders.errors.locked'));
            }

            $editing = [
                ...$this->linesFrom($order, $items),
                'id' => $order->id,
                'number' => $order->number,
                'deliveryDate' => $order->requested_delivery_date?->toDateString(),
                'customerNote' => $order->customer_note,
                'until' => $this->changeUntil($order, 'portal.order.edit_until'),
            ];
        }

        return Inertia::render('Order/Create', [
            ...$catalogue,
            'reorder' => $editing ? null : $this->reorderLines($request, $user, $items),
            'editing' => $editing,
            'delivery' => $this->delivery(DeliveryCalendar::for($user->customer)),
        ]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $this->validated($request);

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

    public function update(Request $request, Order $order, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $this->validated($request);

        $order = $placeOrder->update(
            $order,
            $this->user($request),
            $data['lines'],
            $data['requested_delivery_date'] ?? null,
            $data['customer_note'] ?? null,
            $data['notes'] ?? [],
        );

        return redirect()
            ->route('portal.orders.show', $order)
            ->with('success', __('portal.order.changed', ['number' => $order->number]))
            ->with('orderPlaced', true);
    }

    public function cancel(Request $request, Order $order, PlaceOrder $placeOrder): RedirectResponse
    {
        $placeOrder->cancel($order, $this->user($request));

        return redirect()
            ->route('portal.orders.show', $order)
            ->with('success', __('portal.orders.cancelled', ['number' => $order->number]));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*' => ['numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('customer_id', $this->user($request)->customer_id)
            ->with('customerUser', 'customer')
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

        $order->load('items', 'customer', 'customerUser');

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
                    'quantity' => Money::quantity($item->billedQuantity(), $item->unit),
                    'ordered' => $item->deliveredDiffers() ? __('orders.ordered', ['quantity' => Money::quantity($item->quantity, $item->unit)]) : null,
                    'unitPrice' => $item->unit_price === null ? null : Money::format($item->unit_price).' / '.$item->unit->short(),
                    'lineTotal' => $item->line_total === null ? null : Money::format($item->line_total),
                ])->all(),
            ],
            'deliveryNoteUrl' => $order->status === OrderStatus::Delivered ? route('portal.orders.delivery-note', $order) : null,
            'changeUntil' => $this->changeUntil($order, 'portal.orders.change_until'),
            'justPlaced' => (bool) $request->session()->get('orderPlaced'),
        ]);
    }

    /** The printable leveringsbon, once the order has gone out. */
    public function deliveryNote(Request $request, Order $order, DeliveryNotes $notes): HttpResponse
    {
        abort_unless($order->customer_id === $this->user($request)->customer_id && $order->status === OrderStatus::Delivered, 404);

        return response($notes->render([$order], __('orders.delivery_note.title').' '.$order->number, app()->getLocale()));
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
            'changeable' => OrderChanges::allowed($order),
        ];
    }

    /**
     * "Order again": map the old order's products onto what the customer can
     * order today, notes included. Products no longer on offer are named, so
     * the customer knows to look for a replacement. Lines and notes are keyed
     * by price list item id, like the basket.
     *
     * @return array{lines: array<int, float>, notes: array<int, string>, missing: list<string>}|null
     */
    private function reorderLines(Request $request, CustomerUser $user, Collection $items): ?array
    {
        $orderId = $request->integer('reorder');

        if (! $orderId) {
            return null;
        }

        $order = Order::with('items')->where('customer_id', $user->customer_id)->find($orderId);

        return $order ? $this->linesFrom($order, $items) : null;
    }

    /**
     * An order's lines as a basket. Match on product only: today's price may
     * come from a different list.
     *
     * @return array{lines: array<int, float>, notes: array<int, string>, missing: list<string>}
     */
    private function linesFrom(Order $order, Collection $items): array
    {
        $available = $items->keyBy('productId');
        $basket = ['lines' => [], 'notes' => [], 'missing' => []];

        foreach ($order->items as $orderItem) {
            $item = $available->get($orderItem->product_id);

            if (! $item) {
                $basket['missing'][] = $orderItem->t('product_name');

                continue;
            }

            $basket['lines'][$item['id']] = (float) $orderItem->quantity;
            if (filled($orderItem->note)) {
                $basket['notes'][$item['id']] = $orderItem->note;
            }
        }

        return $basket;
    }

    /** "You can change this until Monday 16:00", or null when that is no longer possible. */
    private function changeUntil(Order $order, string $key): ?string
    {
        if (! OrderChanges::allowed($order)) {
            return null;
        }

        $deadline = OrderChanges::deadline($order);

        return $deadline
            ? __($key, ['deadline' => $deadline->translatedFormat('l j F H:i')])
            : __($key.'_confirmed');
    }

    private function user(Request $request): CustomerUser
    {
        return $request->user('customer');
    }
}
