@php
    use App\Support\Money;

    $day = $list->date->locale('nl')->translatedFormat('l j F Y');
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dagoverzicht {{ $list->date->format('d-m-Y') }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font: 11pt/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111; background: #fff; }
        h1 { font-size: 18pt; margin: 0 0 2mm; }
        h2 { font-size: 11pt; margin: 6mm 0 1mm; text-transform: uppercase; letter-spacing: .05em; color: #555; }
        .meta { color: #555; margin: 0 0 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 2mm 2mm; border-bottom: 1px solid #ccc; vertical-align: top; }
        th { font-size: 9pt; text-transform: uppercase; color: #555; border-bottom: 2px solid #111; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .qty { font-weight: 700; font-size: 12pt; }
        .tick { width: 8mm; }
        .tick span { display: inline-block; width: 4mm; height: 4mm; border: 1px solid #111; }
        .weighed { width: 22mm; border-bottom: 1px dotted #999; }
        .small { font-size: 9pt; color: #555; }
        .note { border: 1px solid #111; padding: 2mm 3mm; margin: 3mm 0; }
        .sheet { break-before: page; page-break-before: always; margin-top: 10mm; padding-top: 6mm; border-top: 2px dashed #bbb; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
        .toolbar button { font: inherit; padding: 8px 16px; border-radius: 6px; border: 1px solid #0f5f7a; background: #0f5f7a; color: #fff; cursor: pointer; }
        @media print { body { padding: 0; } .toolbar { display: none; } .sheet { margin-top: 0; padding-top: 0; border-top: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Afdrukken</button>
    </div>

    <h1>Inkooplijst — {{ $day }}</h1>
    <p class="meta">
        {{ $list->orders->count() }} bestelling(en) voor {{ $list->orders->pluck('customer_id')->unique()->count() }} klant(en)
        @if ($list->unconfirmed() > 0) · <strong>{{ $list->unconfirmed() }} nog niet bevestigd</strong> @endif
        · afgedrukt {{ now()->format('d/m/Y H:i') }}
    </p>

    @forelse ($totals as $category => $lines)
        <h2>{{ $category }}</h2>
        <table>
            <thead>
                <tr><th class="tick"></th><th>Product</th><th class="num">Totaal</th><th class="num">Best.</th><th>Opmerkingen</th></tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td class="tick"><span></span></td>
                        <td>{{ $line['name'] }}</td>
                        <td class="num qty">{{ $line['quantity'] }}</td>
                        <td class="num">{{ $line['orders'] }}</td>
                        <td class="small">{!! collect($line['notes'])->map(fn ($note) => e($note))->implode('<br>') !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Geen bestellingen om te leveren op deze dag.</p>
    @endforelse

    @foreach ($list->orders as $order)
        <section class="sheet">
            <h1>{{ $order->customer->name }}</h1>
            <p class="meta">
                Bestelling {{ $order->number }} · levering {{ $day }}
                @if ($order->customer->address())<br>{{ $order->customer->address() }}@endif
                @if ($order->customer->phone)<br>Tel. {{ $order->customer->phone }}@endif
                @if ($order->customer->contact_name)<br>Contact: {{ $order->customer->contact_name }}@endif
            </p>

            @if ($order->customer->delivery_instructions)
                <div class="note"><strong>Levering:</strong> {{ $order->customer->delivery_instructions }}</div>
            @endif
            @if ($order->customer_note)
                <div class="note"><strong>Opmerking klant:</strong> {{ $order->customer_note }}</div>
            @endif

            <table>
                <thead>
                    <tr><th class="tick"></th><th class="num">Besteld</th><th class="num">Geleverd</th><th>Product</th><th>Opmerking</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="tick"><span></span></td>
                            <td class="num qty">{{ Money::quantity($item->quantity, $item->unit, 'nl') }}</td>
                            <td class="num weighed">{{ $item->delivered_quantity === null ? '' : Money::quantity($item->delivered_quantity, $item->unit, 'nl') }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td class="small">{{ $item->note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
