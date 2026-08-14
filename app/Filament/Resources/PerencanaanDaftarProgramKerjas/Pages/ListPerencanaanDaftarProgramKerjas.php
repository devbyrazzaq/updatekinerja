<?php

namespace App\Filament\Resources\PerencanaanDaftarProgramKerjas\Pages;

use App\Filament\Resources\DaftarProgramKerjas\Pages\ListDaftarProgramKerjas;
use App\Filament\Resources\PerencanaanDaftarProgramKerjas\PerencanaanDaftarProgramKerjaResource;
use App\Filament\Resources\PerencanaanDaftarProgramKerjas\Widgets\PerencanaanDaftarProgramKerjaOverview;
use App\Services\KonteksProgramKerja;
use Illuminate\Contracts\Support\Htmlable;

class ListPerencanaanDaftarProgramKerjas extends ListDaftarProgramKerjas
{
    protected static string $resource = PerencanaanDaftarProgramKerjaResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = KonteksProgramKerja::tahunSlot(PerencanaanDaftarProgramKerjaResource::slotTahunKerja());

        return $tahunKerja === null
            ? 'Belum ada tahun kerja yang direncanakan. Tetapkan tahun perencanaan di Pengaturan Program Kerja.'
            : 'Program kerja yang ditawarkan untuk '.$tahunKerja->name.'. Ajukan untuk mengusulkan pelaksanaan tahun mendatang.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function ringkasanWidgets(): array
    {
        return [
            PerencanaanDaftarProgramKerjaOverview::class,
        ];
    }
}
