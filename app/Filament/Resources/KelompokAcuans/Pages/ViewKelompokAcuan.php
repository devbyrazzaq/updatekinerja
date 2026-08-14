<?php

namespace App\Filament\Resources\KelompokAcuans\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\KelompokAcuans\KelompokAcuanResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewKelompokAcuan extends ViewRecord
{
    protected static string $resource = KelompokAcuanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail '.$this->record->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            KelompokAcuanResource::getUrl() => 'Data Kelompok Acuan Program Kerja',
            '' => 'Detail '.$this->record->name,
        ];
    }

    /**
     * @return array<int, AuthorizedEditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
