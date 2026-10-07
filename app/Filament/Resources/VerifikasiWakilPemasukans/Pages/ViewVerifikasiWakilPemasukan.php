<?php

namespace App\Filament\Resources\VerifikasiWakilPemasukans\Pages;

use App\Filament\Actions\RevisiPemasukanAction;
use App\Filament\Actions\SetujuiPemasukanAction;
use App\Filament\Actions\TolakPemasukanAction;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiWakilPemasukan extends ViewRecord
{
    protected static string $resource = VerifikasiWakilPemasukanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pemasukan Unit';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            SetujuiPemasukanAction::make(),
            ActionGroup::make([
                RevisiPemasukanAction::make(),
                TolakPemasukanAction::make(),
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
            VerifikasiWakilPemasukanResource::getUrl() => 'Verifikasi Wakil Rektor',
            '' => 'Detail',
        ];
    }
}
