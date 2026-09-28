<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Support\PickingList;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A plain page for paper: the purchase list, then one sheet per customer. */
class PrintPickingList
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('viewAny', Order::class), 403);

        $validated = $request->validate(['date' => ['required', 'date']]);
        $list = PickingList::for(CarbonImmutable::parse($validated['date'])->startOfDay(), $request->user());

        return view('admin.picking-list-print', ['list' => $list, 'totals' => $list->totals()]);
    }
}
