<?php

namespace App\Filament\Resources\VerifikasiRektorPemasukans\Pages;

use App\Filament\Actions\RevisiPemasukanAction;
use App\Filament\Actions\SetujuiPemasukanAction;
use App\Filament\Actions\TolakPemasukanAction;
use App\Filament\Resources\VerifikasiRektorPemasukans\VerifikasiRektorPemasukanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiRektorPemasukan extends ViewRecord
{
    protected static string $resource = VerifikasiRektorPemasukanResource::class;

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
            RevisiPemasukanAction::make(),
            TolakPemasukanAction::make(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            VerifikasiRektorPemasukanResource::getUrl() => 'Verifikasi Rektor',
            '' => 'Detail',
        ];
    }
}
