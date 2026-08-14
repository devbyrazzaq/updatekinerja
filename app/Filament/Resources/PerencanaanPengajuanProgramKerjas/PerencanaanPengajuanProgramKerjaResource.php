<?php

namespace App\Filament\Resources\PerencanaanPengajuanProgramKerjas;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages\CreatePerencanaanPengajuanProgramKerja;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages\EditPerencanaanPengajuanProgramKerja;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages\ListPerencanaanPengajuanProgramKerjas;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages\ViewPerencanaanPengajuanProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Resources\Pages\PageRegistration;
use UnitEnum;

/**
 * Salinan Pengajuan Program Kerja untuk slot tahun Perencanaan. Form, tabel, dan
 * alur pengajuannya sama persis dengan menu di grup Pelaksanaan — yang berbeda hanya
 * tahun kerja yang dijangkau, termasuk daftar program kerja yang boleh dipilih.
 */
class PerencanaanPengajuanProgramKerjaResource extends PengajuanProgramKerjaResource
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Perencanaan';
    }

    public static function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Perencanaan;
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
            'index' => ListPerencanaanPengajuanProgramKerjas::route('/'),
            'create' => CreatePerencanaanPengajuanProgramKerja::route('/create'),
            'view' => ViewPerencanaanPengajuanProgramKerja::route('/{record}'),
            'edit' => EditPerencanaanPengajuanProgramKerja::route('/{record}/edit'),
        ];
    }
}
