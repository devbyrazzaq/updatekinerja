<?php

namespace App\Filament\Resources\VerifikasiWakilRektors\Pages;

use App\Filament\Resources\Concerns\HasSetujuiRealisasiAction;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiWakilRektor extends ViewRecord
{
    use HasSetujuiRealisasiAction;

    protected static string $resource = VerifikasiWakilRektorResource::class;

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
            $this->setujuiRealisasiAction(),
            $this->revisiRealisasiAction(),
            $this->tolakRealisasiAction(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            VerifikasiWakilRektorResource::getUrl() => 'Verifikasi Wakil Rektor',
            '' => 'Detail',
        ];
    }
}
