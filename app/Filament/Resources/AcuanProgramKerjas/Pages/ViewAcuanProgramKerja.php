<?php

namespace App\Filament\Resources\AcuanProgramKerjas\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\AcuanProgramKerjas\AcuanProgramKerjaResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAcuanProgramKerja extends ViewRecord
{
    protected static string $resource = AcuanProgramKerjaResource::class;

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
            AcuanProgramKerjaResource::getUrl() => 'Data Acuan Program Kerja',
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
