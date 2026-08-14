<?php

namespace App\Filament\Resources\Pemasukans\Pages;

use App\Filament\Actions\AjukanPemasukanAction;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\UnggahBuktiPemasukanAction;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Models\Pemasukan;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPemasukan extends ViewRecord
{
    protected static string $resource = PemasukanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pemasukan';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            PemasukanResource::getUrl() => 'Data Pemasukan Unit',
            '' => 'Detail',
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AjukanPemasukanAction::make(),
            UnggahBuktiPemasukanAction::make(),
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (Pemasukan $record): bool => $record->dapatDiubah()),
        ];
    }
}
