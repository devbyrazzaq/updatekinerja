<?php

namespace App\Filament\Resources\VerifikasiKeuanganPemasukans\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiKeuanganPemasukans extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiKeuanganPemasukanResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi Biro Keuangan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pemasukan unit yang menunggu verifikasi Biro Keuangan.';
    }
}
