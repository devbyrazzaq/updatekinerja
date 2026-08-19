<?php

namespace App\Providers\Filament;

use App\Http\Controllers\DokumenRealisasiController;
use App\Models\Setting;
use App\Services\KodeDokumenRealisasi;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->font('Plus Jakarta Sans')
            // Brand disusun sendiri agar nama aplikasi, logo, dan keterangan cakupan
            // unit kerja bisa diatur lewat Pengaturan Sistem tanpa mengubah kode.
            ->brandName(fn (): string => Setting::brandNama())
            ->brandLogo(fn (): View => view('filament.app-logo'))
            ->brandLogoHeight('auto')
            ->login(fn () => redirect('/'))
            ->databaseTransactions()
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                'Master Data',
                'Anggaran',
                'Program Kerja',
                'Pelaksanaan',
                'Pemasukan',
                'Verifikasi Pemasukan',
                'Verifikasi Pengajuan',
                'Verifikasi Realisasi',
                'Perencanaan',
                'Verifikasi Pengajuan Perencanaan',
                'Monitoring',
                'Pengguna',
                'Manajemen Akses',
                'Pengaturan Sistem',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            // Pengalih unit kerja untuk pengguna yang berwenang atas lebih dari satu
            // unit; komponennya sendiri yang menentukan perlu tampil atau tidak.
            // Diletakkan di atas menu navigasi sidebar agar tetap rapi di layar kecil,
            // karena sidebar berubah menjadi drawer pada tampilan mobile.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => Blade::render('<livewire:unit-kerja-switcher />'),
            )
            // Penukar kode dokumen realisasi yang tertanam pada berkas ekspor.
            // Didaftarkan sebagai rute terautentikasi supaya tautan yang dibuka orang
            // yang belum masuk singgah dulu di halaman login.
            ->authenticatedRoutes(function (): void {
                Route::get(KodeDokumenRealisasi::NAMA_RUTE.'/{kode}', DokumenRealisasiController::class)
                    ->name(KodeDokumenRealisasi::NAMA_RUTE);
            })
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
