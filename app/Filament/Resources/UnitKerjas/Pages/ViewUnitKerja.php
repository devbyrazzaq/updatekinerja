<?php

namespace App\Filament\Resources\UnitKerjas\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\UnitKerjas\UnitKerjaResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewUnitKerja extends ViewRecord
{
    protected static string $resource = UnitKerjaResource::class;

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
            UnitKerjaResource::getUrl() => 'Data Unit Kerja',
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
