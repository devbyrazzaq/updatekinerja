<?php

namespace App\Filament\Resources\VerifikasiRektors\Pages;

use App\Filament\Resources\Concerns\HasSetujuiRealisasiAction;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiRektor extends ViewRecord
{
    use HasSetujuiRealisasiAction;

    protected static string $resource = VerifikasiRektorResource::class;

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
            ActionGroup::make([
                $this->revisiRealisasiAction(),
                $this->tolakRealisasiAction(),
            ])
                ->label('Keputusan Lain')
                ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            VerifikasiRektorResource::getUrl() => 'Verifikasi Rektor',
            '' => 'Detail',
        ];
    }
}
