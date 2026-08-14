<?php

namespace App\Filament\Resources\VerifikasiWakilRektors\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiWakilRektors extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiWakilRektorResource::class;

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
        return 'Realisasi program kerja yang menunggu persetujuan Wakil Rektor.';
    }
}
