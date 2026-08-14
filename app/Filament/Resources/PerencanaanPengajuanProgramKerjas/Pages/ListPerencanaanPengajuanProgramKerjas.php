<?php

namespace App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages;

use App\Filament\Resources\PengajuanProgramKerjas\Pages\ListPengajuanProgramKerjas;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\PerencanaanPengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Widgets\PerencanaanPengajuanProgramKerjaOverview;
use App\Services\KonteksProgramKerja;
use Illuminate\Contracts\Support\Htmlable;

class ListPerencanaanPengajuanProgramKerjas extends ListPengajuanProgramKerjas
{
    protected static string $resource = PerencanaanPengajuanProgramKerjaResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = KonteksProgramKerja::tahunSlot(PerencanaanPengajuanProgramKerjaResource::slotTahunKerja());

        return $tahunKerja === null
            ? 'Belum ada tahun kerja yang direncanakan. Tetapkan tahun perencanaan di Pengaturan Program Kerja.'
            : 'Pengajuan program kerja yang disusun untuk '.$tahunKerja->name.'.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PerencanaanPengajuanProgramKerjaOverview::class,
        ];
    }
}
