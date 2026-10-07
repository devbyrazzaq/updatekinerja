<?php

namespace App\Filament\Resources\VerifikasiLaporanLampaus\Pages;

use App\Filament\Actions\RevisiLaporanRealisasiAction;
use App\Filament\Actions\TerimaLaporanRealisasiAction;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiLaporanLampau extends ViewRecord
{
    protected static string $resource = VerifikasiLaporanLampauResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Realisasi Program Kerja';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            TerimaLaporanRealisasiAction::make()
                ->successRedirectUrl(VerifikasiLaporanLampauResource::getUrl('index')),
            RevisiLaporanRealisasiAction::make()
                ->successRedirectUrl(VerifikasiLaporanLampauResource::getUrl('index')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            VerifikasiLaporanLampauResource::getUrl() => 'Verifikasi Laporan Lampau',
            '' => 'Detail',
        ];
    }
}
