<x-filament-widgets::widget>
    <x-filament::section heading="Geschiedenis" collapsible>
        <style>
            .oh-list { display: grid; gap: 1rem; font-size: .875rem; }
            .oh-item { display: grid; gap: .125rem; padding-left: .75rem; border-left: 2px solid var(--gray-200); }
            .dark .oh-item { border-color: var(--gray-700); }
            .oh-item.oh-customer { border-color: var(--primary-500); }
            .oh-meta { color: var(--gray-500); font-size: .8125rem; }
            .oh-lines { margin: .25rem 0 0; padding-left: 1rem; list-style: disc; }
        </style>

        @if ($events->isEmpty())
            <p class="oh-meta">Nog niets gewijzigd sinds deze geschiedenis bijgehouden wordt.</p>
        @else
            <div class="oh-list">
                @foreach ($events as $event)
                    <div @class(['oh-item', 'oh-customer' => $event->byCustomer()])>
                        <div><strong>{{ $event->title() }}</strong></div>
                        <div class="oh-meta">
                            {{ $event->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            @if ($event->actor_name)
                                · {{ $event->actor_name }}{{ $event->byCustomer() ? ' (klant)' : '' }}
                            @endif
                        </div>
                        @if ($lines = $event->lines())
                            <ul class="oh-lines">
                                @foreach ($lines as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
