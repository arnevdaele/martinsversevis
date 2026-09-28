<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Support\PickingList as Day;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/** What to buy and what to pack for one delivery day. */
class PickingList extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Bestellingen';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Dagoverzicht';

    protected static ?string $title = 'Dagoverzicht';

    protected static ?string $slug = 'dagoverzicht';

    protected string $view = 'filament.pages.picking-list';

    #[Url]
    public ?string $date = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Order::class) ?? false;
    }

    public function mount(): void
    {
        $this->date = $this->day()->toDateString();
    }

    public function day(): CarbonImmutable
    {
        try {
            return $this->date ? CarbonImmutable::parse($this->date)->startOfDay() : Day::nextDate(auth()->user());
        } catch (Throwable) {
            return Day::nextDate(auth()->user());
        }
    }

    public function getSubheading(): string
    {
        return 'Levering op '.$this->day()->locale('nl')->translatedFormat('l j F Y');
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $previous = Day::neighbour($this->day(), $user, later: false);
        $next = Day::neighbour($this->day(), $user, later: true);

        return [
            Action::make('previous')
                ->label('Vorige')
                ->icon(Heroicon::ChevronLeft)
                ->color('gray')
                ->tooltip($previous ? 'Vorige leverdag met bestellingen' : 'Geen eerdere bestellingen')
                ->disabled($previous === null)
                ->action(fn () => $this->date = $previous?->toDateString()),
            Action::make('next')
                ->label('Volgende')
                ->icon(Heroicon::ChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->tooltip($next ? 'Volgende leverdag met bestellingen' : 'Geen latere bestellingen')
                ->disabled($next === null)
                ->action(fn () => $this->date = $next?->toDateString()),
            Action::make('print')
                ->label('Afdrukken')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn () => route('filament.admin.picking-list.print', ['date' => $this->day()->toDateString()]), shouldOpenInNewTab: true),
        ];
    }

    protected function getViewData(): array
    {
        $day = Day::for($this->day(), auth()->user());

        return [
            'list' => $day,
            'totals' => $day->totals(),
            'withoutDate' => Day::withoutDate(auth()->user()),
        ];
    }
}
