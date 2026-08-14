<?php

namespace App\Filament\Resources\PerencanaanDaftarProgramKerjas\Widgets;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\DaftarProgramKerjas\Widgets\DaftarProgramKerjaOverview;

/**
 * Ringkasan Daftar Program Kerja untuk slot tahun Perencanaan.
 */
class PerencanaanDaftarProgramKerjaOverview extends DaftarProgramKerjaOverview
{
    protected function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Perencanaan;
    }
}
