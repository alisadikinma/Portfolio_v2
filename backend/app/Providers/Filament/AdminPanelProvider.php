<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')
            ->brandName('Ali Sadikin Portfolio')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                // Temporarily disabled to debug
                // \App\Filament\Admin\Resources\Projects\ProjectResource::class,
                // \App\Filament\Admin\Resources\Posts\PostResource::class,
                // \App\Filament\Admin\Resources\Categories\CategoryResource::class,
                // \App\Filament\Admin\Resources\Awards\AwardResource::class,
                // \App\Filament\Admin\Resources\Services\ServiceResource::class,
                // \App\Filament\Admin\Resources\Galleries\GalleryResource::class,
                // \App\Filament\Admin\Resources\Contacts\ContactResource::class,
                // \App\Filament\Admin\Resources\Newsletters\NewsletterResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ]);
    }
}
