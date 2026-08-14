<?php

namespace App\Filament\Resources\VerifikasiWakilPemasukans\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiWakilPemasukans extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiWakilPemasukanResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi Wakil Rektor';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pemasukan unit yang menunggu persetujuan Wakil Rektor.';
    }
}
