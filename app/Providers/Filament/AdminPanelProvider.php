<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdministrarLocales;
use App\Filament\Pages\AdministrarModelos;
use App\Filament\Pages\AgendarCita;
use App\Filament\Pages\AgregarVehiculo;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Campanas;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DashboardKpi;
use App\Filament\Pages\DetalleVehiculo;
use App\Filament\Pages\Home;
use App\Filament\Pages\Kpis;
use App\Filament\Pages\MiCuenta;
use App\Filament\Pages\ProgramacionCitasServicio;
use App\Filament\Pages\ProgramarBloqueo;
use App\Filament\Pages\Vehiculos;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->registration(Register::class)
            ->darkMode(false)
            ->sidebarFullyCollapsibleOnDesktop()
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop(fn () => ! auth()->user()?->hasRole(['super_admin', 'Administrador']))
            ->favicon(asset('favicon.ico'))
            // Logo movido al header
            ->colors([
                'primary' => '#0075BF',
                'secondary' => '#073568',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Home::class, // ✅ Home primero - página por defecto después del login
                Vehiculos::class,
                Dashboard::class,
                AgendarCita::class,
                DetalleVehiculo::class,
                AgregarVehiculo::class,
                Campanas::class,
                ProgramacionCitasServicio::class,
                ProgramarBloqueo::class,
                AdministrarLocales::class,
                AdministrarModelos::class,
                Kpis::class,
                DashboardKpi::class,
                MiCuenta::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render('<div id="corporate-theme-enhancer"></div>'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => \Illuminate\Support\Facades\Auth::check() ? view('customFooter') : '',
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('
                    <link rel="manifest" href="{{ asset("manifest.json") }}">
                    <link rel="icon" type="image/png" href="{{ asset("images/pwa-icon-192.png") }}">
                    <link rel="apple-touch-icon" href="{{ asset("images/pwa-icon-512.png") }}">
                    <meta name="theme-color" content="#ffffff">
                '),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render('<div id="corporate-theme-enhancer"></div>'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => \Illuminate\Support\Facades\Auth::check() ? view('customHeader') : '',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('<img src="{{ asset("images/logomitsui2.svg") }}" alt="Logo Mitsui" class="header-logo" style="height: 48px; width: auto;">'),
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label('Mi cuenta')
                    ->url(fn (): string => MiCuenta::getUrl())
                    ->icon('heroicon-o-user-circle')
                    ->sort(1),
            ]);
    }
}
