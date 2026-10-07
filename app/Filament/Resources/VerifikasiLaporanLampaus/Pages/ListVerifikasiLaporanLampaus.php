<?php

namespace App\Filament\Resources\VerifikasiLaporanLampaus\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use App\Models\TahunKerja;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiLaporanLampaus extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiLaporanLampauResource::class;

    protected function hasRejectedTab(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi Laporan Lampau';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = TahunKerja::penutupan();

        if ($tahunKerja->isEmpty()) {
            return 'Tidak ada tahun kerja yang sedang ditutup, sehingga tidak ada laporan lampau yang perlu diverifikasi.';
        }

        return 'Laporan realisasi '.$tahunKerja->pluck('name')->implode(', ').' yang diunggah unit kerja untuk menuntaskan tunggakannya.';
    }
}
