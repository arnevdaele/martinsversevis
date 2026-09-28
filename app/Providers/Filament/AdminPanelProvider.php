<?php

namespace App\Providers\Filament;

use App\Http\Controllers\Admin\PrintDeliveryNotes;
use App\Http\Controllers\Admin\PrintPickingList;
use App\Support\BackgroundHealth;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('Martins Verse Vis')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Bestellingen',
                'Klanten',
                'Catalogus',
                'Levering',
                'Beheer',
            ])
            ->navigationItems([
                NavigationItem::make('Klantenportaal')
                    ->url(fn () => route('portal.login'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->group('Beheer')
                    ->sort(99),
            ])
            // Mails silently stop when a background container does; say so where staff look.
            ->renderHook(PanelsRenderHook::CONTENT_START, function () {
                $problems = auth()->user()?->can('failed-mails.view') ? BackgroundHealth::problems() : [];

                return $problems ? view('filament.background-health', ['problems' => $problems]) : '';
            })
            // Paper for buying, packing and delivering, outside the panel layout.
            ->authenticatedRoutes(function () {
                Route::get('dagoverzicht/afdrukken', PrintPickingList::class)->name('picking-list.print');
                Route::get('dagoverzicht/leveringsbonnen', [PrintDeliveryNotes::class, 'day'])->name('delivery-notes.day');
                Route::get('bestellingen/{order}/leveringsbon', [PrintDeliveryNotes::class, 'order'])->name('delivery-notes.order');
            })
            ->colors([
                // Sea-blue, matching the portal.
                'primary' => Color::hex('#0f5f7a'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
