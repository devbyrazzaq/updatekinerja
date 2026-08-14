<?php

namespace App\Filament\Resources\Periodes\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\Periodes\PeriodeResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPeriode extends ViewRecord
{
    protected static string $resource = PeriodeResource::class;

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
            PeriodeResource::getUrl() => 'Data Periode',
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
