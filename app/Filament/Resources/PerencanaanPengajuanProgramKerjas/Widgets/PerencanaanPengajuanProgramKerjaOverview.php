<?php

namespace App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Widgets;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\PengajuanProgramKerjas\Widgets\PengajuanProgramKerjaOverview;

/**
 * Ringkasan Pengajuan Program Kerja untuk slot tahun Perencanaan.
 */
class PerencanaanPengajuanProgramKerjaOverview extends PengajuanProgramKerjaOverview
{
    protected function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Perencanaan;
    }
}
