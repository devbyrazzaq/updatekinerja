<?php

namespace App\Filament\Resources\PerencanaanVerifikasiPengajuans;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\PerencanaanVerifikasiPengajuans\Pages\ListPerencanaanVerifikasiPengajuans;
use App\Filament\Resources\PerencanaanVerifikasiPengajuans\Pages\ViewPerencanaanVerifikasiPengajuan;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Services\KonteksProgramKerja;
use Filament\Resources\Pages\PageRegistration;
use UnitEnum;

/**
 * Salinan Verifikasi Pengajuan untuk slot tahun Perencanaan. Tahap, tab, dan aksi
 * verifikasinya sama persis dengan menu di grup Verifikasi Pengajuan — yang berbeda
 * hanya tahun kerja yang dijangkau, sehingga verifikator tahun berjalan dan
 * verifikator tahun mendatang bisa dipisahkan lewat permission masing-masing.
 */
class PerencanaanVerifikasiPengajuanResource extends VerifikasiPengajuanResource
{
    protected static ?string $navigationLabel = 'Verifikasi Pengajuan';

    protected static ?string $pluralLabel = 'Verifikasi Pengajuan Perencanaan';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Pengajuan Perencanaan';
    }

    /**
     * Warna merah khusus antrean verifikasi tahun berjalan; pengajuan perencanaan
     * tetap memakai warna peringatan biasa.
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Perencanaan;
    }

    public static function getPermissionHeading(): string
    {
        return 'Verifikasi Pengajuan Perencanaan';
    }

    /**
     * Selama belum ada tahun yang direncanakan, menu ini tidak punya data apa pun
     * untuk ditampilkan sehingga disembunyikan dari sidebar.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && KonteksProgramKerja::tahunSlot(static::slotTahunKerja()) !== null;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPerencanaanVerifikasiPengajuans::route('/'),
            'view' => ViewPerencanaanVerifikasiPengajuan::route('/{record}'),
        ];
    }
}
