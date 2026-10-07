<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Models\Setting;
use Filament\Widgets\Widget;

/**
 * Kartu "tentang sistem" di samping kartu akun pada dashboard: logo, nama aplikasi,
 * dan nama instansi.
 *
 * Ketiganya dibaca dari pengaturan Identitas Aplikasi — satu-satunya sumbernya, dan
 * tidak ada satu kalimat pun yang ditulis mati di sini. Kampus yang mengganti logo atau
 * namanya cukup menyimpannya sekali di pengaturan, dan brand panel, judul tab, kepala
 * dokumen laporan, serta kartu ini ikut berubah bersamaan.
 *
 * Dipasang tidak-lazy dan sort -2 supaya ia berdampingan dengan AccountWidget (-3) pada
 * baris pertama dashboard dua kolom, bukan muncul belakangan setelah kartunya sempat
 * kosong.
 */
class TentangSistemWidget extends Widget
{
    use HasWidgetAuthorization;

    protected string $view = 'filament.widgets.tentang-sistem';

    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public function nama(): string
    {
        return Setting::brandNama();
    }

    public function instansi(): string
    {
        return Setting::brandInstansi();
    }

    /**
     * URL logo brand, jatuh ke logo bawaan bila belum ada unggahan sehingga kartunya
     * tidak pernah menyisakan kotak gambar kosong.
     */
    public function logo(): string
    {
        return Setting::brandLogoUrl() ?? asset('images/logo-umla.jpg');
    }
}
