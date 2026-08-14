<?php

namespace App\Filament\Resources\PerencanaanVerifikasiPengajuans\Pages;

use App\Filament\Resources\PerencanaanVerifikasiPengajuans\PerencanaanVerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ListVerifikasiPengajuans;
use App\Services\KonteksProgramKerja;
use Illuminate\Contracts\Support\Htmlable;

class ListPerencanaanVerifikasiPengajuans extends ListVerifikasiPengajuans
{
    protected static string $resource = PerencanaanVerifikasiPengajuanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi Pengajuan Perencanaan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = KonteksProgramKerja::tahunSlot(PerencanaanVerifikasiPengajuanResource::slotTahunKerja());

        return $tahunKerja === null
            ? 'Belum ada tahun kerja yang direncanakan. Tetapkan tahun perencanaan di Pengaturan Program Kerja.'
            : 'Pengajuan program kerja '.$tahunKerja->name.' yang menunggu verifikasi tahap 1.';
    }
}
