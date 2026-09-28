<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font: 10pt/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111; background: #fff; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
        .toolbar button { font: inherit; padding: 8px 16px; border-radius: 6px; border: 1px solid #0f5f7a; background: #0f5f7a; color: #fff; cursor: pointer; }
        .sheet { max-width: 190mm; }
        .sheet + .sheet { break-before: page; page-break-before: always; margin-top: 10mm; padding-top: 8mm; border-top: 2px dashed #bbb; }
        .head { display: flex; justify-content: space-between; gap: 8mm; flex-wrap: wrap; margin-bottom: 8mm; }
        .company strong { font-size: 13pt; }
        .doc { text-align: right; }
        .doc h1 { font-size: 18pt; margin: 0 0 2mm; }
        .muted { color: #555; }
        .small { font-size: 8.5pt; color: #555; }
        .customer { border: 1px solid #bbb; border-radius: 2mm; padding: 3mm 4mm; margin-bottom: 6mm; max-width: 95mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 1.8mm 2mm; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { font-size: 8.5pt; text-transform: uppercase; color: #555; border-bottom: 2px solid #111; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .blank { display: inline-block; min-width: 18mm; border-bottom: 1px dotted #999; }
        .totals { display: flex; justify-content: flex-end; margin-top: 5mm; }
        .totals table { width: auto; min-width: 85mm; }
        .totals td { border-bottom: 0; padding: 1mm 2mm; }
        .totals .grand td { border-top: 2px solid #111; font-weight: 700; font-size: 11pt; padding-top: 2mm; }
        .notice { margin-top: 4mm; font-style: italic; }
        .sign { display: flex; gap: 10mm; margin-top: 12mm; }
        .sign div { flex: 1; border-top: 1px solid #111; padding-top: 1.5mm; font-size: 8.5pt; color: #555; }
        @media print { body { padding: 0; } .toolbar { display: none; } .sheet + .sheet { margin-top: 0; padding-top: 0; border-top: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('orders.delivery_note.print') }}</button>
    </div>

    @foreach ($sheets as $sheet)
        {!! $sheet !!}
    @endforeach
</body>
</html>
