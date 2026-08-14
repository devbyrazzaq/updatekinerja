<?php

namespace App\Filament\Resources\VerifikasiLaporans\Pages;

use App\Filament\Actions\RevisiLaporanRealisasiAction;
use App\Filament\Actions\TerimaLaporanRealisasiAction;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiLaporan extends ViewRecord
{
    protected static string $resource = VerifikasiLaporanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Realisasi Program Kerja';
    }

    /**
     * Aksi verifikasi yang sama dengan footer modal detail pada tabel, dengan tambahan
     * kembali ke daftar setelah laporan diputuskan karena record tidak lagi menunggu
     * tahap ini.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            TerimaLaporanRealisasiAction::make()
                ->successRedirectUrl(VerifikasiLaporanResource::getUrl('index')),
            RevisiLaporanRealisasiAction::make()
                ->successRedirectUrl(VerifikasiLaporanResource::getUrl('index')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            VerifikasiLaporanResource::getUrl() => 'Verifikasi Laporan',
            '' => 'Detail',
        ];
    }
}
