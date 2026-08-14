<?php

namespace App\Filament\Resources\VerifikasiBiroKeuangans\Pages;

use App\Filament\Resources\Concerns\HasVerificationStageTabs;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVerifikasiBiroKeuangans extends ListRecords
{
    use HasVerificationStageTabs;

    protected static string $resource = VerifikasiBiroKeuanganResource::class;

    protected function getPendingTabLabel(): string
    {
        return 'Perlu Diproses';
    }

    protected function getRespondedTabLabel(): string
    {
        return 'Sudah Diproses';
    }

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
        return 'Verifikasi Biro Keuangan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Realisasi yang disetujui Wakil Rektor: jadwalkan pencairannya, lalu tandai dicairkan bila anggaran sudah diberikan ke unit kerja. Setelah dicairkan, realisasi menunggu laporan dan pindah ke tab Sudah Diproses.';
    }
}
