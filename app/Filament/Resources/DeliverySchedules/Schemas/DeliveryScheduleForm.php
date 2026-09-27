<?php

namespace App\Filament\Resources\DeliverySchedules\Schemas;

use App\Models\DeliverySchedule;
use App\Support\DeliveryCalendar;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * The schedule reads like the sentence it encodes: "we deliver on Tuesday,
 * order before Monday 16:00". Each weekday is one row, and a live preview
 * shows the dates a customer would be offered right now.
 */
class DeliveryScheduleForm
{
    public const WEEKDAYS = [1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make('Leverdagen')
                        ->description('Zet de dagen aan waarop je levert, en kies tot wanneer de klant daarvoor kan bestellen.')
                        ->headerActions([self::presetAction()])
                        ->schema([
                            Grid::make(12)->schema([
                                Text::make('Leverdag')->columnSpan(4)->weight('medium')->color('gray'),
                                Text::make('Bestellen tot')->columnSpan(5)->weight('medium')->color('gray'),
                                Text::make('Uur')->columnSpan(3)->weight('medium')->color('gray'),
                            ]),
                            ...array_map(fn (int $weekday) => self::dayRow($weekday), array_keys(self::WEEKDAYS)),
                        ]),

                    Section::make('Zo ziet de klant het nu')
                        ->description('De eerstvolgende leverdagen die een klant kan kiezen, met sluitingen en extra dagen meegerekend. Past zich aan terwijl je wijzigt.')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            TextEntry::make('preview')
                                ->hiddenLabel()
                                ->state(fn (Get $get) => self::preview((array) $get('days'), (int) $get('horizon_days'))),
                        ]),
                ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Schema')
                            ->schema([
                                TextInput::make('name')->label('Naam')->required()->maxLength(255)->placeholder('bv. Horeca-ronde'),
                                Textarea::make('description')->label('Omschrijving (intern)')->rows(2),
                                Toggle::make('is_default')
                                    ->label('Standaardschema')
                                    ->helperText('Geldt voor elke klant zonder eigen schema of schema via het klanttype. Er is altijd maar één standaard.')
                                    ->default(fn () => ! DeliverySchedule::where('is_default', true)->exists()),
                            ]),

                        Section::make('Voorwaarden')
                            ->schema([
                                TextInput::make('horizon_days')
                                    ->label('Hoe ver vooruit bestellen')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(120)
                                    ->default(21)
                                    ->required()
                                    ->suffix('dagen')
                                    ->live(onBlur: true),
                                TextInput::make('minimum_order_amount')
                                    ->label('Minimumbedrag per bestelling')
                                    ->helperText('Excl. btw. Producten aan dagprijs tellen niet mee. Leeg = geen minimum.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('€'),
                            ]),

                    ]),
            ]);
    }

    private static function dayRow(int $weekday): Grid
    {
        $enabled = fn (Get $get) => (bool) $get("days.{$weekday}.enabled");

        return Grid::make(12)->schema([
            Toggle::make("days.{$weekday}.enabled")
                ->label(self::WEEKDAYS[$weekday])
                // A new schedule starts from a common week rather than seven blank rows.
                ->default(in_array($weekday, [2, 3, 4, 5, 6], true))
                ->live()
                ->columnSpan(4),
            Select::make("days.{$weekday}.cutoff_days")
                ->hiddenLabel()
                ->options(self::cutoffOptions($weekday))
                ->default(1)
                ->selectablePlaceholder(false)
                ->native(false)
                ->live()
                ->disabled(fn (Get $get) => ! $enabled($get))
                ->dehydrated()
                ->columnSpan(5),
            TimePicker::make("days.{$weekday}.cutoff_time")
                ->hiddenLabel()
                ->seconds(false)
                ->default('16:00')
                ->live(onBlur: true)
                ->disabled(fn (Get $get) => ! $enabled($get))
                ->dehydrated()
                ->columnSpan(3),
        ]);
    }

    /** "1 dag vooraf (maandag)": the weekday in brackets saves doing the arithmetic. */
    private static function cutoffOptions(int $weekday): array
    {
        $options = [];

        foreach (range(0, 6) as $daysBefore) {
            $day = strtolower(self::WEEKDAYS[(($weekday - $daysBefore - 1 + 7) % 7) + 1]);
            $options[$daysBefore] = match ($daysBefore) {
                0 => "Dezelfde dag ({$day})",
                1 => "1 dag vooraf ({$day})",
                default => "{$daysBefore} dagen vooraf ({$day})",
            };
        }

        return $options;
    }

    private static function presetAction(): Action
    {
        return Action::make('preset')
            ->label('Snel invullen')
            ->icon('heroicon-o-bolt')
            ->link()
            ->modalHeading('Alle leverdagen in één keer instellen')
            ->modalDescription('Overschrijft de dagen hierboven. Je kan daarna nog per dag bijsturen.')
            ->schema([
                CheckboxList::make('weekdays')
                    ->label('Leverdagen')
                    ->options(self::WEEKDAYS)
                    ->default([2, 3, 4, 5, 6])
                    ->columns(4)
                    ->required(),
                Grid::make(2)->schema([
                    Select::make('cutoff_days')
                        ->label('Bestellen tot')
                        ->options([0 => 'Dezelfde dag', 1 => '1 dag vooraf', 2 => '2 dagen vooraf', 3 => '3 dagen vooraf'])
                        ->default(1)
                        ->required(),
                    TimePicker::make('cutoff_time')->label('Uur')->seconds(false)->default('16:00')->required(),
                ]),
            ])
            ->action(function (array $data, Set $set) {
                foreach (array_keys(self::WEEKDAYS) as $weekday) {
                    $set("days.{$weekday}", [
                        'enabled' => in_array((string) $weekday, array_map('strval', $data['weekdays']), true),
                        'cutoff_days' => (int) $data['cutoff_days'],
                        'cutoff_time' => substr((string) $data['cutoff_time'], 0, 5),
                    ]);
                }
            });
    }

    public static function preview(array $days, int $horizon): HtmlString
    {
        $options = DeliveryCalendar::fromRules($days, $horizon ?: 21)->options();

        if ($options === []) {
            return new HtmlString('<span style="color: var(--danger-600)">Geen enkele leverdag beschikbaar: klanten kunnen zo niet bestellen.</span>');
        }

        $rows = collect($options)->take(10)->map(function (array $option) {
            $date = Carbon::parse($option['date'])->locale('nl')->translatedFormat('l j F');
            $cutoff = Carbon::parse($option['cutoff'])->locale('nl')->translatedFormat('D j M \o\m H:i');
            $extra = $option['extra']
                ? ' <span style="font-size:.75rem;font-weight:600;color:var(--success-600)">extra leverdag</span>'
                : '';

            return '<tr><td style="padding:.3rem 1.5rem .3rem 0;font-weight:600;white-space:nowrap">'.e(ucfirst($date)).$extra.'</td>'
                .'<td style="padding:.3rem 0;opacity:.75">bestellen tot '.e($cutoff).'</td></tr>';
        })->implode('');

        return new HtmlString("<table style=\"width:100%\"><tbody>{$rows}</tbody></table>");
    }

    /** "di, wo, do — bestellen 1 dag vooraf tot 16:00" for the table. */
    public static function summary(array $days): string
    {
        $on = collect($days)->filter(fn (array $day) => $day['enabled']);

        if ($on->isEmpty()) {
            return 'Geen leverdagen';
        }

        $names = $on->keys()->map(fn (int $weekday) => mb_substr(strtolower(self::WEEKDAYS[$weekday]), 0, 2))->implode(', ');
        $deadlines = $on->map(fn (array $day) => $day['cutoff_days'].'|'.$day['cutoff_time'])->unique();

        if ($deadlines->count() > 1) {
            return "{$names} · verschillende deadlines";
        }

        $first = $on->first();
        $when = match ($first['cutoff_days']) {
            0 => 'dezelfde dag',
            1 => '1 dag vooraf',
            default => "{$first['cutoff_days']} dagen vooraf",
        };

        return "{$names} · bestellen {$when} tot {$first['cutoff_time']}";
    }
}
