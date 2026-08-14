<?php

namespace App\Filament\Resources\VerifikasiRektorPemasukans\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiRektorPemasukans\VerifikasiRektorPemasukanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiRektorPemasukans extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiRektorPemasukanResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi Rektor';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pemasukan unit yang menunggu persetujuan Rektor.';
    }
}
