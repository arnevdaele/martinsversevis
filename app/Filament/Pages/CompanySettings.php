<?php

namespace App\Filament\Pages;

use App\Support\CompanyDetails;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Our own details, printed at the top of every delivery note. */
class CompanySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Bedrijfsgegevens';

    protected static ?string $title = 'Bedrijfsgegevens';

    protected static ?string $slug = 'bedrijfsgegevens';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.update') ?? false;
    }

    public function mount(): void
    {
        $details = CompanyDetails::get();

        $this->form->fill([...$details, 'address' => implode("\n", $details['address'])]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hoofding van de leveringsbon')
                    ->description('Zo staan jullie bovenaan elke leveringsbon, in het portaal en op papier.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Bedrijfsnaam')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label('Btw-nummer')
                            ->placeholder('BE0123.456.789')
                            ->maxLength(50),
                        Textarea::make('address')
                            ->label('Adres')
                            ->helperText('Eén regel per lijn, bv. straat en nummer, dan postcode en gemeente.')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('phone')
                            ->label('Telefoon')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Opslaan')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        CompanyDetails::save($this->form->getState());

        Notification::make()->success()->title('Bedrijfsgegevens opgeslagen')->send();
    }
}
