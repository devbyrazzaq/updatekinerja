<?php

namespace App\Providers\Filament;

use App\Http\Controllers\DokumenRealisasiController;
use App\Models\Setting;
use App\Services\KodeDokumenRealisasi;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
            // Selain warna utama, palet tambahan didaftarkan agar tiap status realisasi
            // punya warnanya sendiri tanpa ada yang kembar
            // (lihat App\Enums\EnumStatusRealisasi::getColor()).
            ->colors([
                'primary' => Color::Amber,
                'indigo' => Color::Indigo,
                'violet' => Color::Violet,
                'cyan' => Color::Cyan,
                'teal' => Color::Teal,
                'orange' => Color::Orange,
                'rose' => Color::Rose,
                'slate' => Color::Slate,
            ])
            // Sidebar bisa diciutkan di desktop. Saat ciut, hanya ikon grup yang tampil
            // dan anak menunya muncul sebagai dropdown ketika ikon grup disorot; ikon
            // tiap menu otomatis disembunyikan Filament saat sidebar terbuka.
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Master Data')
                    ->icon(Heroicon::OutlinedCircleStack),
                NavigationGroup::make('Anggaran')
                    ->icon(Heroicon::OutlinedWallet),
                NavigationGroup::make('Program Kerja')
                    ->icon(Heroicon::OutlinedBriefcase),
                NavigationGroup::make('Pelaksanaan')
                    ->icon(Heroicon::OutlinedPlayCircle),
                NavigationGroup::make('Pemasukan')
                    ->icon(Heroicon::OutlinedArrowDownTray),
                NavigationGroup::make('Verifikasi Pemasukan')
                    ->icon(Heroicon::OutlinedReceiptPercent),
                NavigationGroup::make('Verifikasi Pengajuan')
                    ->icon(Heroicon::OutlinedShieldCheck),
                NavigationGroup::make('Verifikasi Realisasi')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck),
                NavigationGroup::make('Perencanaan')
                    ->icon(Heroicon::OutlinedCalendarDays),
                NavigationGroup::make('Verifikasi Pengajuan Perencanaan')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass),
                NavigationGroup::make('Monitoring')
                    ->icon(Heroicon::OutlinedChartBar),
                NavigationGroup::make('Pengguna')
                    ->icon(Heroicon::OutlinedUsers),
                NavigationGroup::make('Manajemen Akses')
                    ->icon(Heroicon::OutlinedKey),
                NavigationGroup::make('Pengaturan Sistem')
                    ->icon(Heroicon::OutlinedCog6Tooth),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
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
