<?php

namespace App\Filament\Pages;

use App\Actions\WeighOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\PickingList as Day;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/** What to buy and what to pack for one delivery day, and what it weighed. */
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

    /** The date picker's own state; Filament keeps a time in it, the URL gets just the day. */
    public ?array $picker = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Order::class) ?? false;
    }

    public function mount(): void
    {
        $this->show($this->day());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')
                ->label('Leverdag')
                ->hiddenLabel()
                ->prefix('Leverdag')
                ->closeOnDateSelection()
                ->live()
                ->afterStateUpdated(fn (?string $state) => $this->date = $state ? CarbonImmutable::parse($state)->toDateString() : null),
        ])->statePath('picker');
    }

    private function show(?CarbonImmutable $day): void
    {
        if ($day === null) {
            return;
        }

        $this->date = $day->toDateString();
        $this->form->fill(['date' => $this->date]);
    }

    public function day(): CarbonImmutable
    {
        try {
            return $this->date ? CarbonImmutable::parse($this->date)->startOfDay() : Day::nextDate(auth()->user());
        } catch (Throwable) {
            return Day::nextDate(auth()->user());
        }
    }

    /** Staff type what a line actually weighed, right in the packing list. */
    public function weigh(int $itemId, ?string $value): void
    {
        $item = OrderItem::query()
            ->whereHas('order', fn ($query) => $query->visibleTo(auth()->user()))
            ->findOrFail($itemId);

        abort_unless(auth()->user()->can('update', $item->order), 403);

        try {
            app(WeighOrderItem::class)->handle($item, $value, auth()->user());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title("Gewicht opgeslagen: {$item->product_name}")->send();
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
                ->action(fn () => $this->show($previous)),
            Action::make('next')
                ->label('Volgende')
                ->icon(Heroicon::ChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->tooltip($next ? 'Volgende leverdag met bestellingen' : 'Geen latere bestellingen')
                ->disabled($next === null)
                ->action(fn () => $this->show($next)),
            Action::make('print')
                ->label('Afdrukken')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn () => route('filament.admin.picking-list.print', ['date' => $this->day()->toDateString()]), shouldOpenInNewTab: true),
            Action::make('delivery-notes')
                ->label('Leveringsbonnen')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->tooltip('Eén leveringsbon per bestelling, om mee te geven')
                ->visible(fn () => Day::for($this->day(), auth()->user())->orders->isNotEmpty())
                ->url(fn () => route('filament.admin.delivery-notes.day', ['date' => $this->day()->toDateString()]), shouldOpenInNewTab: true),
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
