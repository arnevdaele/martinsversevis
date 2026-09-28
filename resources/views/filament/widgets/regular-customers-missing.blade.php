@php
    use App\Filament\Resources\Customers\CustomerResource;
    use App\Support\RegularCustomers;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Vaste klanten die nog niet bestelden"
        :description="'Bestelden in minstens '.RegularCustomers::MIN_WEEKS.' van de laatste '.RegularCustomers::WEEKS.' weken, en hadden deze week meestal al besteld.'"
    >
        {{-- No custom admin theme, so this widget brings its own few rules. --}}
        <style>
            .rc-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
            .rc-table th { text-align: left; font-weight: 600; padding: .5rem .75rem; border-bottom: 1px solid var(--gray-200); }
            .rc-table td { padding: .5rem .75rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
            .rc-table tr:last-child td { border-bottom: 0; }
            .dark .rc-table th, .dark .rc-table td { border-color: var(--gray-800); }
            .rc-table a { font-weight: 600; color: var(--primary-600); }
            .rc-muted { color: var(--gray-500); }
        </style>

        @if ($missing->isEmpty())
            <p class="rc-muted">Alle vaste klanten hebben al besteld, of het is nog te vroeg in de week.</p>
        @else
            <div style="overflow-x: auto">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Klant</th>
                            <th>Bestelt meestal</th>
                            <th>Laatste bestelling</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($missing as $row)
                            <tr>
                                <td>
                                    @can('update', $row['customer'])
                                        <a href="{{ CustomerResource::getUrl('edit', ['record' => $row['customer']]) }}">{{ $row['customer']->name }}</a>
                                    @else
                                        <strong>{{ $row['customer']->name }}</strong>
                                    @endcan
                                    <div class="rc-muted">{{ $row['customer']->type?->name }} · {{ $row['weeks'] }} van de {{ RegularCustomers::WEEKS }} weken</div>
                                </td>
                                <td>{{ $row['usually']->translatedFormat('l') }} rond {{ $row['usually']->format('H') }}u</td>
                                <td>{{ $row['lastOrder']->translatedFormat('D j M') }}</td>
                                <td>
                                    @if ($row['customer']->phone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $row['customer']->phone) }}">{{ $row['customer']->phone }}</a>
                                    @endif
                                    @if ($row['customer']->contact_name)
                                        <div class="rc-muted">{{ $row['customer']->contact_name }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
