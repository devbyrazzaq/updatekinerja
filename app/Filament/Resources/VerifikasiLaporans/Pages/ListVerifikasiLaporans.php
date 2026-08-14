<?php

namespace App\Filament\Resources\VerifikasiLaporans\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiLaporans extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiLaporanResource::class;

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
        return 'Verifikasi Laporan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Laporan realisasi program kerja yang menunggu verifikasi.';
    }
}
