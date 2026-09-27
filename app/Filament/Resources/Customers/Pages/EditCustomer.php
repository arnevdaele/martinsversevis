<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Models\PriceList;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        // Soft delete only: orders keep pointing at the customer. There is no
        // force delete, because a customer with order history must survive.
        return [
            $this->ownPricesAction(),
            DeleteAction::make()->label('Archiveren'),
            RestoreAction::make(),
        ];
    }

    /**
     * A customer's exception prices live in a list linked to them alone. The
     * button opens that list, creating it (empty) the first time.
     */
    private function ownPricesAction(): Action
    {
        $ownList = fn (): ?PriceList => $this->record->extraPriceLists()->whereDoesntHave('customerTypes')->first();

        return Action::make('ownPrices')
            ->label(fn () => $ownList() ? 'Eigen prijzen' : 'Eigen prijzen instellen')
            ->icon('heroicon-o-currency-euro')
            ->color('gray')
            ->visible(fn () => $ownList() ? auth()->user()->can('price-lists.view') : auth()->user()->can('create', PriceList::class))
            ->requiresConfirmation(fn () => $ownList() === null)
            ->modalHeading('Eigen prijzen voor deze klant')
            ->modalDescription('Er komt een prijslijst die enkel voor deze klant geldt. Vul daarin alleen de producten in die een andere prijs krijgen; voor al de rest blijft de prijs van het klanttype gelden.')
            ->modalSubmitActionLabel('Aanmaken')
            ->action(function () use ($ownList) {
                $list = $ownList();

                if (! $list) {
                    $list = PriceList::create(['name' => "Prijzen {$this->record->name}"]);
                    $list->customers()->attach($this->record);
                }

                $this->redirect(PriceListResource::getUrl('prices', ['record' => $list]));
            });
    }
}
