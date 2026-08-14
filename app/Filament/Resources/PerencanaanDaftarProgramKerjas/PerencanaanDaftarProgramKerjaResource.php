<?php

namespace App\Filament\Resources\PerencanaanDaftarProgramKerjas;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\PerencanaanDaftarProgramKerjas\Pages\ListPerencanaanDaftarProgramKerjas;
use App\Filament\Resources\PerencanaanDaftarProgramKerjas\Pages\ViewPerencanaanDaftarProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Resources\Pages\PageRegistration;
use UnitEnum;

/**
 * Salinan Daftar Program Kerja untuk slot tahun Perencanaan. Isi tabel, infolist,
 * dan aksinya sama persis dengan menu di grup Pelaksanaan — yang berbeda hanya tahun
 * kerja yang dijangkau, sehingga penyusunan tahun mendatang tidak pernah bercampur
 * dengan tahun yang sedang berjalan.
 */
class PerencanaanDaftarProgramKerjaResource extends DaftarProgramKerjaResource
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
            'index' => ListPerencanaanDaftarProgramKerjas::route('/'),
            'view' => ViewPerencanaanDaftarProgramKerja::route('/{record}'),
        ];
    }
}
