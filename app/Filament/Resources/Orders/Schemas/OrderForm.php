<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** What staff can change on an order. Lines are edited in the items table below. */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Behandeling')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('status')->label('Status')->options(OrderStatus::class)->required()->native(false),
                    DatePicker::make('requested_delivery_date')->label('Leverdatum')->native(false)->displayFormat('d/m/Y'),
                    Select::make('handled_by')->label('Behandeld door')->relationship('handler', 'name')->searchable()->preload(),
                    Textarea::make('internal_note')
                        ->label('Interne notitie')
                        ->helperText('De klant ziet dit niet.')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
