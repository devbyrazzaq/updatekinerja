<?php

namespace App\Filament\Resources\VerifikasiPengajuans\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiPengajuans extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiPengajuanResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi Pengajuan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pengajuan program kerja yang menunggu verifikasi tahap 1.';
    }
}
