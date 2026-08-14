<?php

namespace App\Filament\Resources\VerifikasiRektors\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiRektors extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiRektorResource::class;

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
        return 'Realisasi program kerja yang menunggu persetujuan Rektor.';
    }
}
