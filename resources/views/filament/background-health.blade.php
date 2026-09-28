{{-- Shown at the top of every admin page while background work has stopped; see App\Support\BackgroundHealth. --}}
@foreach ($problems as $problem)
    <x-filament::callout
        color="danger"
        icon="heroicon-o-exclamation-triangle"
        heading="Achtergrondtaken liggen stil"
        :description="$problem"
    />
@endforeach
